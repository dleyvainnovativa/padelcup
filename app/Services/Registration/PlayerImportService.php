<?php

namespace App\Services\Registration;

use App\Models\Category;
use App\Models\Player;
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
 *     leader (optional — any non-empty value marks the pair as a group leader)
 *
 * SINGLES category: one row = one player.
 *   Columns:
 *     player1_name (required), player1_email, player1_phone,
 *     leader (optional)
 *   player2_* columns, if present, are ignored.
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
     * @return array{rows: array<int, array>, errors: array<int, string>}
     */
    public function parse(string $path, bool $isSingles = false): array
    {
        $rows = [];
        $errors = [];

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
                // Singles: only player 1 is needed.
                if ($p1Name === '') {
                    $errors[] = "Línea {$lineNo}: cada jugador necesita un nombre.";
                    continue;
                }
            } else {
                // Doubles: both players required.
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

            // Player 2 only for doubles. Kept null-free so downstream code that
            // checks isset($row['player2']) can branch cleanly.
            if (! $isSingles) {
                $entry['player2'] = [
                    'name' => $p2Name,
                    'email' => trim((string) ($row['player2_email'] ?? '')) ?: null,
                    'phone' => trim((string) ($row['player2_phone'] ?? '')) ?: null,
                ];
            }

            $rows[] = $entry;
        }

        fclose($handle);

        return ['rows' => $rows, 'errors' => $errors];
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
     * @return array{imported: int, skipped: int}
     */
    public function commit(array $rows, Category $category, User $manager): array
    {
        $isSingles = $category->isSingles();
        $imported = 0;
        $skipped = 0;

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

            // Doubles: build player 2. Singles: pass an empty def —
            // createManagerPair ignores it for singles categories.
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
                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }
}
