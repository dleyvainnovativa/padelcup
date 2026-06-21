<?php

namespace App\Services\Registration;

use App\Enums\CategoryFormat;
use App\Models\Category;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Tournament-wide bulk import. Parses a flat file/paste where each row is one
 * pair with a leading category column:
 *   category, player1_name, player1_email, player1_phone,
 *             player2_name, player2_email, player2_phone
 *
 * Groups rows by category, previews counts (and which categories are new vs
 * existing), then creates missing categories (with defaults) and commits pairs
 * via the existing per-category PlayerImportService.
 */
class TournamentImportService
{
    public function __construct(private PlayerImportService $playerImport) {}

    /**
     * Parse CSV text (from an uploaded file's contents OR a pasted textarea)
     * into [categoryName => [pairRows...]] plus errors.
     *
     * @return array{groups: array<string, array<int,array>>, errors: array<int,string>, total: int}
     */
    public function parse(string $csvText): array
    {
        $groups = [];
        $errors = [];
        $total = 0;

        $lines = preg_split('/\r\n|\r|\n/', trim($csvText));
        if (empty($lines) || (count($lines) === 1 && trim($lines[0]) === '')) {
            return ['groups' => [], 'errors' => ['El archivo o texto está vacío.'], 'total' => 0];
        }

        $header = null;
        $lineNo = 0;

        foreach ($lines as $rawLine) {
            $lineNo++;
            if (trim($rawLine) === '') continue;

            $data = str_getcsv($rawLine);

            if ($header === null) {
                $header = array_map(fn($h) => strtolower(trim($h)), $data);
                // Validate required columns exist.
                $required = ['category', 'player1_name', 'player2_name'];
                $missing = array_diff($required, $header);
                if (! empty($missing)) {
                    return ['groups' => [], 'errors' => [
                        'Faltan columnas requeridas: ' . implode(', ', $missing) . '. '
                            . 'Encabezado esperado: category, player1_name, player1_email, player1_phone, player2_name, player2_email, player2_phone',
                    ], 'total' => 0];
                }
                continue;
            }

            $row = array_combine($header, array_pad($data, count($header), null));

            $category = trim((string) ($row['category'] ?? ''));
            $p1Name = trim((string) ($row['player1_name'] ?? ''));
            $p2Name = trim((string) ($row['player2_name'] ?? ''));

            if ($category === '') {
                $errors[] = "Línea {$lineNo}: falta la categoría.";
                continue;
            }
            if ($p1Name === '' || $p2Name === '') {
                $errors[] = "Línea {$lineNo}: cada pareja necesita dos jugadores con nombre.";
                continue;
            }

            $groups[$category][] = [
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
            $total++;
        }

        return ['groups' => $groups, 'errors' => $errors, 'total' => $total];
    }

    /**
     * Build a preview: per category, pair count + whether it already exists in
     * the tournament + unique player count.
     *
     * @return array<int,array{category:string, pairs:int, players:int, exists:bool}>
     */
    public function preview(Tournament $tournament, array $groups): array
    {
        $existingNames = $tournament->categories()->pluck('name')
            ->mapWithKeys(fn($n) => [mb_strtolower(trim($n)) => true])
            ->all();

        $out = [];
        foreach ($groups as $categoryName => $rows) {
            $players = collect($rows)->flatMap(fn($r) => [
                mb_strtolower($r['player1']['name']),
                mb_strtolower($r['player2']['name']),
            ])->unique()->count();

            $out[] = [
                'category' => $categoryName,
                'pairs' => count($rows),
                'players' => $players,
                'exists' => isset($existingNames[mb_strtolower(trim($categoryName))]),
            ];
        }

        return $out;
    }

    /**
     * Commit: create missing categories (defaults) then import pairs into each.
     *
     * @return array{categories_created:int, imported:int, skipped:int}
     */
    public function commit(Tournament $tournament, array $groups, User $manager): array
    {
        $created = 0;
        $imported = 0;
        $skipped = 0;

        foreach ($groups as $categoryName => $rows) {
            $category = $tournament->categories()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($categoryName))])
                ->first();

            if (! $category) {
                // Sequential tint: existing count + 1 (model wraps via % 6).
                $tint = $tournament->categories()->count() + 1;
                $category = $this->createCategoryWithDefaults($tournament, $categoryName, $tint);
                $created++;
            }

            $result = $this->playerImport->commit($rows, $category, $manager);
            $imported += $result['imported'];
            $skipped += $result['skipped'];
        }

        return ['categories_created' => $created, 'imported' => $imported, 'skipped' => $skipped];
    }

    /** A new category with sensible defaults the manager can edit later. */
    private function createCategoryWithDefaults(Tournament $tournament, string $name, int $tint = 1): Category
    {
        return $tournament->categories()->create([
            'name' => trim($name),
            'tint' => $tint,
            'format' => CategoryFormat::Hybrid,
            'group_format' => \App\Enums\GroupFormat::RoundRobin,
            'preferred_group_size' => 3,
            'advance_per_group' => 2,
            'extra_qualifiers' => 0,
            'min_pairs' => 2,
            'max_pairs' => null,
            'price_centavos' => 0,
            'has_third_place' => false,
        ]);
    }
}
