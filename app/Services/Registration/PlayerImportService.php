<?php

namespace App\Services\Registration;

use App\Models\Category;
use App\Models\Player;
use App\Models\PlayerAvailability;
use App\Models\User;
use App\Services\Registration\RegistrationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Parses an uploaded CSV of competitors and imports them into a category.
 *
 * DOUBLES category: one row = one pair (two players).
 *   Columns (header row, case-insensitive):
 *     player1_name (required), player1_email, player1_phone,
 *     player2_name (required), player2_email, player2_phone,
 *     leader (optional — any non-empty value marks the pair as a group leader),
 *     schedule (optional — JSON per-day availability for PLAYER 1, see below)
 *
 * SINGLES category: one row = one player.
 *   Columns:
 *     player1_name (required), player1_email, player1_phone,
 *     leader (optional), schedule (optional)
 *   player2_* columns, if present, are ignored.
 *
 * SCHEDULE column (optional): JSON mapping play day → "available FROM" time OR
 * time, e.g.  {"2026-10-04":"09:00","2026-10-05":"08:30"}
 *   - The row already identifies the player, so the key is the DAY only.
 *   - Applies to PLAYER 1 only (in doubles, player 2 is untouched).
 *   - Each day OVERWRITES that player's existing availability for that day.
 *   - Days outside the tournament's play days are rejected (that day skipped,
 *     row flagged in preview). Malformed JSON flags the row's schedule and is
 *     skipped, but the player still imports.
 *   - An empty / missing cell is simply skipped (existing rows untouched).
 *
 * The category's play_format decides which shape is expected, so the caller
 * passes it in — the file itself doesn't need a modalidad column here (that's
 * the global/tournament importer's job, where categories can be created on the
 * fly). Committing delegates to RegistrationService so capacity, status, and
 * payment tracking behave exactly like a manual add — and createManagerPair
 * already drops player 2 for singles categories.
 */
class PlayerImportService
{
    public function __construct(private RegistrationService $registrations) {}

    /**
     * Parse a CSV file path into normalized rows.
     *
     * @param  bool  $isSingles  when true, player 2 is neither required nor read.
     * @param  array<int,string>  $playDays  allowed 'Y-m-d' play days for schedule
     *         validation. Empty array = accept any well-formed date.
     * @return array{rows: array<int, array>, errors: array<int, string>}
     */
    public function parse(string $path, bool $isSingles = false, array $playDays = []): array
    {
        $rows = [];
        $errors = [];
        $playDaySet = array_flip($playDays); // fast lookup; empty = accept all

        if (! is_readable($path)) {
            return ['rows' => [], 'errors' => ['No se pudo leer el archivo.']];
        }

        $handle = fopen($path, 'r');
        $header = null;
        $lineNo = 0;

        while (($data = fgetcsv($handle)) !== false) {
            $lineNo++;

            if ($header === null) {
                $header = array_map(fn($h) => strtolower(trim($h)), $data);
                continue;
            }

            if (count(array_filter($data, fn($v) => trim((string) $v) !== '')) === 0) {
                continue; // blank line
            }

            $row = array_combine($header, array_pad($data, count($header), null));

            $p1Name = trim((string) ($row['player1_name'] ?? ''));
            $p2Name = trim((string) ($row['player2_name'] ?? ''));

            if ($isSingles) {
                if ($p1Name === '') {
                    $errors[] = "Línea {$lineNo}: cada jugador necesita un nombre.";
                    continue;
                }
            } else {
                if ($p1Name === '' || $p2Name === '') {
                    $errors[] = "Línea {$lineNo}: cada pareja necesita dos jugadores con nombre.";
                    continue;
                }
            }

            $entry = [
                'line' => $lineNo,
                'leader' => trim((string) ($row['leader'] ?? '')) !== '',
                'player1' => [
                    'name' => $p1Name,
                    'email' => trim((string) ($row['player1_email'] ?? '')) ?: null,
                    'phone' => trim((string) ($row['player1_phone'] ?? '')) ?: null,
                ],
            ];

            if (! $isSingles) {
                $entry['player2'] = [
                    'name' => $p2Name,
                    'email' => trim((string) ($row['player2_email'] ?? '')) ?: null,
                    'phone' => trim((string) ($row['player2_phone'] ?? '')) ?: null,
                ];
            }

            // --- Optional schedule column (JSON, player 1) --------------------
            [$schedule, $scheduleError] = $this->parseScheduleCell(
                $row['schedule'] ?? null,
                $playDaySet
            );
            $entry['schedule'] = $schedule;           // ['Y-m-d' => 'HH:MM', ...]
            if ($scheduleError !== null) {
                $entry['schedule_error'] = $scheduleError; // human string for preview
            }

            $rows[] = $entry;
        }

        fclose($handle);

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Parse one schedule cell into a clean [day => 'HH:MM'] map.
     *
     * @param  array<string,int>  $playDaySet  array_flip of allowed days (empty = any)
     * @return array{0: array<string,string>, 1: ?string}  [cleanMap, errorOrNull]
     */
    private function parseScheduleCell($raw, array $playDaySet): array
    {
        $raw = trim((string) ($raw ?? ''));
        if ($raw === '') {
            return [[], null]; // empty cell — nothing to do, not an error
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [[], 'JSON inválido'];
        }

        $clean = [];
        $bad = [];

        foreach ($decoded as $day => $time) {
            $day = trim((string) $day);

            // Validate day format (Y-m-d).
            $d = \DateTime::createFromFormat('Y-m-d', $day);
            $validFormat = $d && $d->format('Y-m-d') === $day;
            if (! $validFormat) {
                $bad[] = $day;
                continue;
            }

            // Reject days outside the tournament play window (when provided).
            if (! empty($playDaySet) && ! isset($playDaySet[$day])) {
                $bad[] = $day;
                continue;
            }

            $t = strtolower(trim((string) $time));

            // "off" (or "no") marks the player UNAVAILABLE that whole day.
            if ($t === 'off' || $t === 'no') {
                $clean[$day] = 'off';
                continue;
            }

            // Otherwise a time: available FROM. Accept "9:00","09:00","09:00:00".
            if (! preg_match('/^(\d{1,2}):(\d{2})(:\d{2})?$/', $t, $m)) {
                $bad[] = $day;
                continue;
            }
            $h = (int) $m[1];
            $min = (int) $m[2];
            if ($h > 23 || $min > 59) {
                $bad[] = $day;
                continue;
            }
            $clean[$day] = sprintf('%02d:%02d', $h, $min);
        }

        $error = $bad ? ('Días/horas inválidos: ' . implode(', ', $bad)) : null;

        return [$clean, $error];
    }

    /** Annotate each player in each row with possible DB duplicates. */
    public function withDuplicateFlags(array $rows): Collection
    {
        return collect($rows)->map(function (array $row) {
            $row['player1']['possible_duplicates'] = $this->dupesFor($row['player1']);
            if (isset($row['player2'])) {
                $row['player2']['possible_duplicates'] = $this->dupesFor($row['player2']);
            }
            return $row;
        });
    }

    private function dupesFor(array $player): array
    {
        return Player::query()
            ->where(function ($q) use ($player) {
                $q->where('normalized_name', Player::normalize($player['name']));
                if (filled($player['email'])) $q->orWhere('email', $player['email']);
                if (filled($player['phone'])) $q->orWhere('phone', $player['phone']);
            })
            ->limit(5)
            ->get(['id', 'name', 'email', 'phone'])
            ->toArray();
    }

    /**
     * Commit previewed rows into the category. Stops when the category is full,
     * reporting how many were imported vs skipped.
     *
     * Singles vs doubles is taken from the category itself, so a row without a
     * player2 (singles) commits as a solo pair via createManagerPair, which
     * stamps is_singles and drops player 2.
     *
     * If a row carries a validated `schedule` map, player 1's availability for
     * those days is overwritten after the pair is created.
     *
     * @return array{imported: int, skipped: int, schedule_days: int}
     */
    public function commit(array $rows, Category $category, User $manager): array
    {
        $isSingles = $category->isSingles();
        $tournament = $category->tournament;
        $imported = 0;
        $skipped = 0;
        $scheduleDays = 0;

        foreach ($rows as $row) {
            if ($category->fresh()->isFull()) {
                $skipped++;
                continue;
            }

            $p1 = [
                'player_id' => $row['player1']['link_player_id'] ?? null,
                'name' => $row['player1']['name'],
                'email' => $row['player1']['email'] ?? null,
                'phone' => $row['player1']['phone'] ?? null,
            ];

            $p2 = [];
            if (! $isSingles && isset($row['player2'])) {
                $p2 = [
                    'player_id' => $row['player2']['link_player_id'] ?? null,
                    'name' => $row['player2']['name'],
                    'email' => $row['player2']['email'] ?? null,
                    'phone' => $row['player2']['phone'] ?? null,
                ];
            }

            try {
                $pair = $this->registrations->createManagerPair($category, $p1, $p2, $manager);
                if (($row['leader'] ?? false) && $pair) {
                    $pair->update(['seed' => 1]);
                }

                // Apply player 1's schedule (overwrite matching days).
                $schedule = $row['schedule'] ?? [];
                if ($pair && is_array($schedule) && $schedule) {
                    $scheduleDays += $this->applySchedule($tournament, $p1['name'], $schedule);
                }

                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'schedule_days' => $scheduleDays];
    }

    /**
     * Overwrite a player's availability for the given days.
     *
     * Availability is keyed by normalized_name (not player_id), so this works
     * whether the player is newly created or linked to an existing one. Each day
     * is upserted: existing row for (tournament, name, day) is replaced with the
     * new "from" time; latest_time is cleared (from-only, per spec).
     *
     * @param  array<string,string>  $schedule  ['Y-m-d' => 'HH:MM']
     * @return int  number of day-rows written
     */
    private function applySchedule($tournament, string $playerName, array $schedule): int
    {
        $normalized = Player::normalize($playerName);
        $written = 0;

        foreach ($schedule as $day => $from) {
            $isOff = ($from === 'off');
            PlayerAvailability::updateOrCreate(
                [
                    'tournament_id' => $tournament->id,
                    'normalized_name' => $normalized,
                    'day' => $day,
                ],
                [
                    'earliest_time' => $isOff ? '00:00' : $from,
                    'latest_time' => null,        // from-only per spec
                    'unavailable' => $isOff,      // "off" → cannot play that day
                ]
            );
            $written++;
        }

        return $written;
    }
}
