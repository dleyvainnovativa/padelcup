<?php

namespace App\Services\Registration;

use App\Models\Category;
use App\Models\Player;
use App\Models\User;
use App\Services\Registration\RegistrationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Parses an uploaded CSV of PAIRS and imports them into a category.
 * One row = one pair (two players), matching how amateur padel registers.
 *
 * Expected CSV columns (header row, case-insensitive):
 *   player1_name (required), player1_email, player1_phone,
 *   player2_name (required), player2_email, player2_phone
 *
 * Each player gets a duplicate check; the manager resolves link-vs-create on
 * the preview screen. Committing delegates to RegistrationService so capacity,
 * status, and payment tracking all behave exactly like a manual add.
 */
class PlayerImportService
{
    public function __construct(private RegistrationService $registrations) {}

    /**
     * Parse a CSV file path into normalized pair rows.
     *
     * @return array{rows: array<int, array>, errors: array<int, string>}
     */
    public function parse(string $path): array
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

            if ($p1Name === '' || $p2Name === '') {
                $errors[] = "Línea {$lineNo}: cada pareja necesita dos jugadores con nombre.";
                continue;
            }

            $rows[] = [
                'line' => $lineNo,
                'player1' => [
                    'name' => $p1Name,
                    'email' => trim((string) ($row['player1_email'] ?? '')) ?: null,
                    'phone' => trim((string) ($row['player1_phone'] ?? '')) ?: null,
                ],
                'player2' => [
                    'name' => $p2Name,
                    'email' => trim((string) ($row['player2_email'] ?? '')) ?: null,
                    'phone' => trim((string) ($row['player2_phone'] ?? '')) ?: null,
                ],
            ];
        }

        fclose($handle);

        return ['rows' => $rows, 'errors' => $errors];
    }

    /** Annotate each player in each pair row with possible DB duplicates. */
    public function withDuplicateFlags(array $rows): Collection
    {
        return collect($rows)->map(function (array $row) {
            $row['player1']['possible_duplicates'] = $this->dupesFor($row['player1']);
            $row['player2']['possible_duplicates'] = $this->dupesFor($row['player2']);
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
     * Commit previewed pair rows into the category. Stops when the category is
     * full, reporting how many were imported vs skipped.
     *
     * Each row's player def may carry link_player_id to reuse an existing
     * player instead of creating one.
     *
     * @return array{imported: int, skipped: int}
     */
    public function commit(array $rows, Category $category, User $manager): array
    {
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
            $p2 = [
                'player_id' => $row['player2']['link_player_id'] ?? null,
                'name' => $row['player2']['name'],
                'email' => $row['player2']['email'] ?? null,
                'phone' => $row['player2']['phone'] ?? null,
            ];

            try {
                $this->registrations->createManagerPair($category, $p1, $p2, $manager);
                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }
}
