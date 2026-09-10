<?php

namespace App\Services\Registration;

use App\Enums\CategoryFormat;
use App\Enums\CategoryPlayFormat;
use App\Models\Category;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Str;
use App\Enums\GroupFormat;

/**
 * Tournament-wide bulk import. Parses a flat file/paste where each row is one
 * competitor line with a leading category column:
 *   category, player1_name, player1_email, player1_phone,
 *             player2_name, player2_email, player2_phone,
 *             play_format (optional: doubles|singles), leader (optional)
 *
 * play_format is OPTIONAL and per-row, but a category is one modality, so the
 * parser resolves a single play_format per category:
 *   - if any row in a category names a play_format, that decides the category;
 *   - conflicting values within one category are reported as an error;
 *   - absent → doubles (the default), preserving existing behaviour.
 *
 * For SINGLES categories, player 2 is neither required nor read. Groups rows by
 * category, previews, creates missing categories (stamping play_format), and
 * commits via the per-category PlayerImportService (which already routes singles
 * to solo pairs).
 */
class TournamentImportService
{
    public function __construct(
        private PlayerImportService $playerImport,
        private \App\Services\Tournament\GroupGenerationService $groupGen,
        private \App\Services\Tournament\BracketService $brackets,
    ) {}

    /** Normalize a play_format cell to 'singles'|'doubles'|null. */
    private function normalizePlayFormat(?string $raw): ?string
    {
        $v = strtolower(trim((string) $raw));
        if ($v === '') return null;
        if (in_array($v, ['singles', 'single', 'individual', 'sencillo'], true)) return 'singles';
        if (in_array($v, ['doubles', 'double', 'dobles', 'pareja', 'parejas'], true)) return 'doubles';
        return null; // unrecognized → treated as unset
    }

    /**
     * Parse CSV text into [categoryName => [pairRows...]] plus per-category
     * play_format and errors.
     *
     * @return array{groups: array<string, array<int,array>>, formats: array<string,string>, errors: array<int,string>, total: int}
     */
    public function parse(string $csvText): array
    {
        $groups = [];
        $formats = [];       // categoryName(lower) => 'singles'|'doubles'
        $formatSeen = [];    // categoryName(lower) => set of seen values (for conflict detection)
        $errors = [];
        $total = 0;

        $lines = preg_split('/\r\n|\r|\n/', trim($csvText));
        if (empty($lines) || (count($lines) === 1 && trim($lines[0]) === '')) {
            return ['groups' => [], 'formats' => [], 'errors' => ['El archivo o texto está vacío.'], 'total' => 0];
        }

        $header = null;
        $lineNo = 0;

        foreach ($lines as $rawLine) {
            $lineNo++;
            if (trim($rawLine) === '') continue;

            $data = str_getcsv($rawLine);

            if ($header === null) {
                $header = array_map(fn($h) => strtolower(trim($h)), $data);
                // player2_name is NO LONGER globally required — a file may be all
                // singles. Only category + player1_name are structural musts.
                $required = ['category', 'player1_name'];
                $missing = array_diff($required, $header);
                if (! empty($missing)) {
                    return ['groups' => [], 'formats' => [], 'errors' => [
                        'Faltan columnas requeridas: ' . implode(', ', $missing) . '. '
                            . 'Encabezado esperado: category, player1_name, player1_email, player1_phone, '
                            . 'player2_name, player2_email, player2_phone, play_format (opcional: doubles|singles), leader (opcional)',
                    ], 'total' => 0];
                }
                continue;
            }

            $row = array_combine($header, array_pad($data, count($header), null));

            $category = trim((string) ($row['category'] ?? ''));
            $p1Name = trim((string) ($row['player1_name'] ?? ''));
            $p2Name = trim((string) ($row['player2_name'] ?? ''));
            $rowFormat = $this->normalizePlayFormat($row['play_format'] ?? null);

            if ($category === '') {
                $errors[] = "Línea {$lineNo}: falta la categoría.";
                continue;
            }

            $ckey = mb_strtolower(trim($category));

            // Track play_format per category + detect conflicts.
            if ($rowFormat !== null) {
                $formatSeen[$ckey][$rowFormat] = true;
            }

            // Determine the effective modality for THIS row: the row's own value,
            // else whatever the category has settled on so far, else doubles.
            $effective = $rowFormat ?? ($formats[$ckey] ?? 'doubles');
            if ($rowFormat !== null) {
                $formats[$ckey] = $rowFormat;
            } elseif (! isset($formats[$ckey])) {
                $formats[$ckey] = 'doubles';
            }

            if ($p1Name === '') {
                $errors[] = "Línea {$lineNo}: falta el nombre del jugador 1.";
                continue;
            }
            if ($effective === 'doubles' && $p2Name === '') {
                $errors[] = "Línea {$lineNo}: la categoría «{$category}» es de dobles; falta el jugador 2.";
                continue;
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
            if ($effective === 'doubles') {
                $entry['player2'] = [
                    'name' => $p2Name,
                    'email' => trim((string) ($row['player2_email'] ?? '')) ?: null,
                    'phone' => trim((string) ($row['player2_phone'] ?? '')) ?: null,
                ];
            }

            $groups[$category][] = $entry;
            $total++;
        }

        // Report any category with conflicting play_format values.
        foreach ($formatSeen as $ckey => $seen) {
            if (count($seen) > 1) {
                $errors[] = "La categoría «{$ckey}» tiene filas con modalidades distintas (dobles y singles). Usa una sola modalidad por categoría.";
            }
        }

        return ['groups' => $groups, 'formats' => $formats, 'errors' => $errors, 'total' => $total];
    }

    /**
     * Build a preview: per category, count + whether it exists + its modality.
     *
     * @param array<string,string> $formats  categoryName(lower) => 'singles'|'doubles'
     * @return array<int,array{category:string, pairs:int, players:int, leaders:int, exists:bool, play_format:string, format_mismatch:bool}>
     */
    public function preview(Tournament $tournament, array $groups, array $formats = []): array
    {
        $existing = $tournament->categories()->get()
            ->mapWithKeys(fn($c) => [mb_strtolower(trim($c->name)) => $c])
            ->all();

        $out = [];
        foreach ($groups as $categoryName => $rows) {
            $ckey = mb_strtolower(trim($categoryName));
            $fileFormat = $formats[$ckey] ?? 'doubles';
            $isSingles = $fileFormat === 'singles';

            // Player count: 1 per row for singles, else both names.
            $players = collect($rows)->flatMap(function ($r) {
                $names = [mb_strtolower($r['player1']['name'])];
                if (isset($r['player2'])) $names[] = mb_strtolower($r['player2']['name']);
                return $names;
            })->unique()->count();

            // If the category already exists, its stored play_format wins; flag a
            // mismatch so the manager knows the file's column was overridden.
            $existingCat = $existing[$ckey] ?? null;
            $mismatch = false;
            $effectiveFormat = $fileFormat;
            if ($existingCat) {
                $storedSingles = $existingCat->isSingles();
                $effectiveFormat = $storedSingles ? 'singles' : 'doubles';
                $mismatch = ($storedSingles !== $isSingles);
            }

            $out[] = [
                'category' => $categoryName,
                'pairs' => count($rows),
                'players' => $players,
                'leaders' => collect($rows)->filter(fn($r) => $r['leader'] ?? false)->count(),
                'exists' => (bool) $existingCat,
                'play_format' => $effectiveFormat,
                'format_mismatch' => $mismatch,
            ];
        }

        return $out;
    }

    /**
     * Commit: create missing categories (stamping play_format) then import rows.
     *
     * @param array<string,string> $formats  categoryName(lower) => 'singles'|'doubles'
     * @return array{categories_created:int, imported:int, skipped:int, groups_built:int, brackets_built:int}
     */
    public function commit(Tournament $tournament, array $groups, User $manager, array $settings = [], bool $autoGenerate = true, array $formats = []): array
    {
        $created = 0;
        $imported = 0;
        $skipped = 0;
        $groupsBuilt = 0;
        $bracketsBuilt = 0;

        foreach ($groups as $categoryName => $rows) {
            $cfg = $this->settingsFor($settings, $categoryName);
            $ckey = mb_strtolower(trim($categoryName));
            $fileSingles = ($formats[$ckey] ?? 'doubles') === 'singles';

            $category = $tournament->categories()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($categoryName))])
                ->first();

            if (! $category) {
                $tint = $tournament->categories()->count() + 1;
                $category = $this->createCategoryWithDefaults($tournament, $categoryName, $tint, $cfg, $fileSingles);
                $created++;
            } elseif (array_key_exists('format', $cfg)) {
                $chosen = $this->resolveGroupFormat($cfg['format']);
                if ($category->group_format !== $chosen) {
                    $category->group_format = $chosen;
                    $category->save();
                }
            }
            // NOTE: for an EXISTING category we do NOT change its play_format —
            // the stored value wins (the preview flags any mismatch). commit()
            // in PlayerImportService reads $category->isSingles(), so rows are
            // routed by the category's real modality regardless of the file.

            $result = $this->playerImport->commit($rows, $category, $manager);
            $imported += $result['imported'];
            $skipped += $result['skipped'];

            if ($autoGenerate) {
                try {
                    $pairs = $category->poolPairs()->with(['player1', 'player2'])->get();
                    if ($pairs->count() >= 2) {
                        $this->groupGen->generate($category->fresh(), $pairs);
                        $groupsBuilt++;
                        if ($category->format === CategoryFormat::Hybrid) {
                            $this->brackets->buildPositional($category->fresh());
                            $bracketsBuilt++;
                        }
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return [
            'categories_created' => $created,
            'imported' => $imported,
            'skipped' => $skipped,
            'groups_built' => $groupsBuilt,
            'brackets_built' => $bracketsBuilt,
        ];
    }

    private function settingsFor(array $settings, string $categoryName): array
    {
        $key = mb_strtolower(trim($categoryName));
        $found = null;
        foreach ($settings as $name => $cfg) {
            if (mb_strtolower(trim($name)) === $key) {
                $found = $cfg;
                break;
            }
        }

        return [
            'size' => in_array((int) ($found['size'] ?? 3), [3, 4], true) ? (int) $found['size'] : 3,
            'advance' => max(1, min(2, (int) ($found['advance'] ?? 1))),
            'extra' => max(0, min(3, (int) ($found['extra'] ?? 0))),
            'format' => (($found['format'] ?? 'mex') === 'rr') ? 'rr' : 'mex',
        ];
    }

    /** A new category with the chosen (or default) settings + play_format. */
    private function createCategoryWithDefaults(Tournament $tournament, string $name, int $tint = 1, array $cfg = [], bool $isSingles = false): Category
    {
        return $tournament->categories()->create([
            'name' => trim($name),
            'tint' => $tint,
            'format' => CategoryFormat::Hybrid,
            'play_format' => $isSingles ? CategoryPlayFormat::Singles : CategoryPlayFormat::Doubles,
            'group_format' => $this->resolveGroupFormat($cfg['format'] ?? 'mex'),
            'mexicano_pairing' => \App\Enums\MexicanoPairing::Cross,
            'preferred_group_size' => $cfg['size'] ?? 3,
            'advance_per_group' => $cfg['advance'] ?? 1,
            'extra_qualifiers' => $cfg['extra'] ?? 0,
            'min_pairs' => 2,
            'max_pairs' => null,
            'price_centavos' => 0,
            'has_third_place' => false,
        ]);
    }

    /**
     * Map the posted format flag to the GroupFormat enum.
     * 'rr' → RoundRobin; anything else (incl. 'mex'/null) → Mexicano.
     */
    private function resolveGroupFormat(?string $flag): GroupFormat
    {
        return ($flag === 'rr') ? GroupFormat::RoundRobin : GroupFormat::Mexicano;
    }
}
