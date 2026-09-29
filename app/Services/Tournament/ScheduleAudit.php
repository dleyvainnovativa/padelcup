<?php

namespace App\Services\Tournament;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Pure, framework-free schedule audit ("Revisar conflictos").
 *
 * Works on plain arrays (built by SchedulingService::auditRows()) so the rules
 * can be unit-tested from the PHP CLI without Laravel. Three reports:
 *
 *  1. conflicts — per player: overlapping matches / too little rest.
 *     Now also covers matches where the player is only a CANDIDATE (Mexicano
 *     R2 slots still waiting on R1), flagged as 'possible'.
 *
 *  2. load — per player per day: matches that day, split into
 *       sure      → player is confirmed in the match, or a Mexicano R2 "choice"
 *                   whose options all fall on the same day (they play exactly
 *                   one of them — we just don't know which);
 *       possible  → a Mexicano R2 choice whose options fall on different days.
 *     A day is flagged when sure + possible ≥ threshold (default 3).
 *     Bracket matches only count once their pairs are known.
 *
 *  3. order — per category: can the schedule be played in this order?
 *       errors   → a match starts before a match it depends on has ended;
 *                  a match is scheduled but one it depends on is not;
 *                  a bracket first-round match starts before its source
 *                  group(s) finish (A1/B2 → groups A/B, Q# → all groups).
 *       warnings → less than min rest between a match and the one feeding it;
 *                  rounds out of tidy order (groups → … → SF → F).
 *
 * Row shape (one per match, keyed by id):
 *   id, category_id, category, group_id|null, group_letter|null, round|null,
 *   is_third_place, is_bye, confirmed, start|null (unix), end|null,
 *   phase (display: "Grupos", "8F", "SF", "F", "3er lugar"), phase_rank (int),
 *   feeders: [ 'a' => ['id'=>int,'source'=>'winner'|'loser'] | null, 'b' => … ],
 *   seed_labels: ['a' => ?string, 'b' => ?string],
 *   players: ['a' => [key => name], 'b' => [key => name]]   (known players only)
 *   info: ['label'=>, 'short'=> (label without category), 'vs'=>, 'court'=>, 'time'=>]
 */
final class ScheduleAudit
{
    private const DAYS = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    private const MONTHS = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    private DateTimeZone $tz;

    /** @var array<int,array> */
    private array $rows = [];

    public function __construct(
        private int $restSec,
        string $timezone = 'America/Mexico_City',
        private int $dayThreshold = 3,
    ) {
        $this->tz = new DateTimeZone($timezone);
    }

    /**
     * @param array<int,array> $rows              keyed by match id
     * @param array<int,array<string,int>> $groupsByCategory [category_id => [letter => group_id]]
     */
    public function run(array $rows, array $groupsByCategory): array
    {
        $this->rows = $rows;
        $entries = $this->participation();

        return [
            'conflicts' => $this->conflicts($entries),
            'load' => $this->load($entries),
            'order' => $this->order($groupsByCategory),
        ];
    }

    // =====================================================================
    // Participation: who (possibly) plays what
    // =====================================================================

    /**
     * [playerKey => ['name'=>, 'items'=>[ ['mid'=>, 'choice'=>?string] ]]]
     * choice = "f{feederId}" for a Mexicano R2 candidacy (player plays exactly
     * ONE match among all items sharing that choice key), null when sure.
     */
    private function participation(): array
    {
        $out = [];
        foreach ($this->rows as $m) {
            foreach (['a', 'b'] as $side) {
                $known = $m['players'][$side] ?? [];
                if (! empty($known)) {
                    foreach ($known as $key => $name) {
                        $this->addItem($out, $key, $name, $m['id'], null);
                    }
                    continue;
                }

                // Unknown side: only a GROUP feeder (Mexicano R2) makes candidates.
                // Bracket feeders are ignored on purpose ("if they keep winning"
                // would flag almost every pair on finals day).
                $f = $m['feeders'][$side] ?? null;
                if (! $f) continue;
                $feeder = $this->rows[$f['id']] ?? null;
                if (! $feeder || $feeder['group_id'] === null) continue;

                foreach (['a', 'b'] as $fs) {
                    foreach ($feeder['players'][$fs] ?? [] as $key => $name) {
                        $this->addItem($out, $key, $name, $m['id'], 'f' . $feeder['id']);
                    }
                }
            }
        }
        return $out;
    }

    private function addItem(array &$out, string $key, string $name, int $mid, ?string $choice): void
    {
        $out[$key] ??= ['name' => $name, 'items' => []];
        // A sure appearance wins over a candidacy for the same match.
        if (isset($out[$key]['items'][$mid]) && $out[$key]['items'][$mid]['choice'] === null) return;
        $out[$key]['items'][$mid] = ['mid' => $mid, 'choice' => $choice];
    }

    // =====================================================================
    // 1. Overlap / rest conflicts
    // =====================================================================

    private function conflicts(array $entries): array
    {
        $found = [];
        foreach ($entries as $key => $p) {
            $list = [];
            foreach ($p['items'] as $it) {
                $m = $this->rows[$it['mid']];
                if ($m['start'] === null) continue;
                $list[] = $it + ['start' => $m['start'], 'end' => $m['end']];
            }
            if (count($list) < 2) continue;
            usort($list, fn($x, $y) => [$x['start'], $x['mid']] <=> [$y['start'], $y['mid']]);

            $n = count($list);
            for ($i = 0; $i < $n - 1; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $a = $list[$i];
                    $b = $list[$j];
                    $possible = $a['choice'] !== null || $b['choice'] !== null;

                    if ($possible) {
                        // Alternatives of one choice: the player plays only one.
                        if ($a['choice'] !== null && $a['choice'] === $b['choice']) continue;
                        // Direct dependency (R1 → its own R2): the order check owns it.
                        if ($this->isDirectFeeder($a['mid'], $b['mid'])) continue;
                    }

                    $overlap = $a['start'] < $b['end'] && $b['start'] < $a['end'];
                    $rest = ! $overlap && $this->restSec > 0
                        && $b['start'] >= $a['end']
                        && ($b['start'] - $a['end'] < $this->restSec);
                    if (! $overlap && ! $rest) continue;

                    $found[] = [
                        'player' => $p['name'],
                        'severity' => $overlap ? 'overlap' : 'rest',
                        'possible' => $possible,
                        'matches' => [
                            $this->info($a['mid'], $a['choice'] !== null),
                            $this->info($b['mid'], $b['choice'] !== null),
                        ],
                    ];
                }
            }
        }

        // De-dupe (same player + same two matches) — keep the first seen.
        $seen = [];
        $unique = [];
        foreach ($found as $c) {
            $k = $c['player'] . '|' . implode('|', array_map(fn($x) => $x['id'], $c['matches']));
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $unique[] = $c;
        }

        // Sure overlaps → possible overlaps → sure rest → possible rest.
        $rank = fn($c) => ($c['severity'] === 'overlap' ? 0 : 2) + ($c['possible'] ? 1 : 0);
        usort($unique, fn($x, $y) => $rank($x) <=> $rank($y));
        return $unique;
    }

    private function isDirectFeeder(int $x, int $y): bool
    {
        foreach ([[$x, $y], [$y, $x]] as [$parent, $child]) {
            foreach (['a', 'b'] as $s) {
                if (($this->rows[$child]['feeders'][$s]['id'] ?? null) === $parent) return true;
            }
        }
        return false;
    }

    // =====================================================================
    // 2. Matches per player per day
    // =====================================================================

    private function load(array $entries): array
    {
        $out = [];
        foreach ($entries as $key => $p) {
            $days = []; // day => ['sure'=>int,'possible'=>int,'matches'=>[]]

            $choices = [];
            foreach ($p['items'] as $it) {
                $m = $this->rows[$it['mid']];
                if ($it['choice'] !== null) {
                    $choices[$it['choice']][] = $m;
                    continue;
                }
                if ($m['start'] === null) continue;
                $d = $this->dayKey($m['start']);
                $days[$d] ??= ['sure' => 0, 'possible' => 0, 'matches' => []];
                $days[$d]['sure']++;
                $days[$d]['matches'][] = $this->info($m['id'], false) + ['kind' => 'sure', 'alts' => []];
            }

            foreach ($choices as $options) {
                $byDay = [];
                $unscheduled = 0;
                foreach ($options as $m) {
                    if ($m['start'] === null) {
                        $unscheduled++;
                        continue;
                    }
                    $byDay[$this->dayKey($m['start'])][] = $m;
                }
                if (empty($byDay)) continue;

                // All options scheduled on one single day → exactly one match that day.
                $sureDay = ($unscheduled === 0 && count($byDay) === 1);

                foreach ($byDay as $d => $opts) {
                    usort($opts, fn($x, $y) => $x['start'] <=> $y['start']);
                    $days[$d] ??= ['sure' => 0, 'possible' => 0, 'matches' => []];
                    $days[$d][$sureDay ? 'sure' : 'possible']++;
                    $main = array_shift($opts);
                    $days[$d]['matches'][] = $this->info($main['id'], true) + [
                        'kind' => $sureDay ? 'r2_sure' : 'r2_possible',
                        'alts' => array_map(fn($o) => $this->info($o['id'], true), $opts),
                    ];
                }
            }

            foreach ($days as $d => $row) {
                if ($row['sure'] + $row['possible'] < $this->dayThreshold) continue;
                usort($row['matches'], fn($x, $y) => ($x['ts'] ?? 0) <=> ($y['ts'] ?? 0));
                $out[] = [
                    'player' => $p['name'],
                    'day' => $d,
                    'day_label' => $this->dayLabel($d),
                    'sure' => $row['sure'],
                    'possible' => $row['possible'],
                    'total' => $row['sure'] + $row['possible'],
                    'matches' => $row['matches'],
                ];
            }
        }

        // Day, then heaviest first, then name.
        usort($out, fn($x, $y) => [$x['day'], -$x['total'], -$x['sure'], $x['player']]
            <=> [$y['day'], -$y['total'], -$y['sure'], $y['player']]);
        return $out;
    }

    // =====================================================================
    // 3. Order within each category
    // =====================================================================

    private function order(array $groupsByCategory): array
    {
        $byCat = [];
        foreach ($this->rows as $m) {
            $byCat[$m['category_id']][] = $m;
        }

        $report = [];
        foreach ($byCat as $cid => $matches) {
            $scheduled = array_values(array_filter($matches, fn($m) => $m['start'] !== null));
            if (empty($scheduled)) continue;

            $letters = $groupsByCategory[$cid] ?? [];
            $groupMatches = []; // group_id => [rows]
            foreach ($matches as $m) {
                if ($m['group_id'] !== null) $groupMatches[$m['group_id']][] = $m;
            }

            $errors = [];
            $warnings = [];
            $groupIssues = []; // "kind|gid" => ['kind','gid','letter','matches'=>[M],'blocker'=>row|null]

            foreach ($scheduled as $m) {
                if ($m['confirmed']) continue; // already played — nothing to fix

                foreach ($this->dependencies($m, $letters) as $dep) {
                    if (isset($dep['match'])) {
                        $f = $this->rows[$dep['match']];
                        if ($f['confirmed']) continue;
                        $role = $dep['source'] === 'loser' ? 'perdedor' : 'ganador';

                        if ($f['start'] === null) {
                            $errors[] = [
                                'kind' => 'unscheduled_dep',
                                'message' => "{$m['info']['short']} está programado, pero el partido del que sale su {$role} ({$f['info']['short']}) no tiene horario.",
                                'matches' => [$this->info($f['id'], false), $this->info($m['id'], false)],
                            ];
                        } elseif ($m['start'] < $f['end']) {
                            $errors[] = [
                                'kind' => 'before_dep',
                                'message' => "{$m['info']['short']} empieza antes de que termine {$f['info']['short']} (de ahí sale su {$role}).",
                                'matches' => [$this->info($f['id'], false), $this->info($m['id'], false)],
                            ];
                        } elseif ($this->restSec > 0 && $m['start'] - $f['end'] < $this->restSec) {
                            $gap = intdiv($m['start'] - $f['end'], 60);
                            $min = intdiv($this->restSec, 60);
                            $warnings[] = [
                                'kind' => 'rest_dep',
                                'message' => "Solo {$gap} min entre {$f['info']['short']} y {$m['info']['short']} (mínimo {$min}). Los mismos jugadores siguen jugando.",
                                'matches' => [$this->info($f['id'], false), $this->info($m['id'], false)],
                            ];
                        }
                        continue;
                    }

                    // Group dependency (bracket first round seeded from groups).
                    $gid = $dep['group'];
                    $pending = array_filter($groupMatches[$gid] ?? [], fn($g) => ! $g['confirmed']);
                    if (empty($pending)) continue; // group finished

                    $unsched = array_filter($pending, fn($g) => $g['start'] === null);
                    if (! empty($unsched)) {
                        $k = "group_unscheduled|$gid";
                        $groupIssues[$k] ??= ['kind' => 'group_unscheduled', 'gid' => $gid, 'letter' => $dep['letter'], 'matches' => [], 'blocker' => null, 'count' => count($unsched)];
                        $groupIssues[$k]['matches'][$m['id']] = $m;
                        continue;
                    }

                    $last = null;
                    foreach ($pending as $g) {
                        if ($last === null || $g['end'] > $last['end']) $last = $g;
                    }
                    if ($m['start'] < $last['end']) {
                        $k = "before_group|$gid";
                        $groupIssues[$k] ??= ['kind' => 'before_group', 'gid' => $gid, 'letter' => $dep['letter'], 'matches' => [], 'blocker' => $last, 'count' => 0];
                        $groupIssues[$k]['matches'][$m['id']] = $m;
                    }
                }
            }

            foreach ($groupIssues as $gi) {
                $ms = array_values($gi['matches']);
                usort($ms, fn($x, $y) => $x['start'] <=> $y['start']);
                $n = count($ms);
                $phase = $ms[0]['phase'];
                $what = $n === 1 ? "1 partido de {$phase}" : "{$n} partidos de {$phase}";
                $infos = array_map(fn($x) => $this->info($x['id'], false), $ms);

                if ($gi['kind'] === 'group_unscheduled') {
                    $c = $gi['count'];
                    $errors[] = [
                        'kind' => 'group_unscheduled',
                        'message' => "El Grupo {$gi['letter']} tiene {$c} " . ($c === 1 ? 'partido' : 'partidos') . " sin programar, pero {$what} que dependen de él ya " . ($n === 1 ? 'está programado' : 'están programados') . '.',
                        'matches' => $infos,
                    ];
                } else {
                    $b = $gi['blocker'];
                    $errors[] = [
                        'kind' => 'before_group',
                        'message' => ucfirst($what) . ($n === 1 ? ' empieza' : ' empiezan') . " antes de que termine el Grupo {$gi['letter']} (su último partido acaba {$this->timeLabel($b['end'])}).",
                        'matches' => array_merge([$this->info($b['id'], false)], $infos),
                    ];
                }
            }

            // Tidy round order (soft): every phase should finish before the next
            // one starts. Groups are one phase (R1→R2 inside a group is already a
            // hard dependency above; across groups it's often intentional).
            $byRank = [];
            foreach ($scheduled as $m) {
                if ($m['is_bye']) continue;
                $byRank[$m['phase_rank']][] = $m;
            }
            ksort($byRank);
            $ranks = array_keys($byRank);
            for ($i = 0; $i < count($ranks) - 1; $i++) {
                $prev = $byRank[$ranks[$i]];
                $next = $byRank[$ranks[$i + 1]];
                $last = null;
                foreach ($prev as $x) if ($last === null || $x['end'] > $last['end']) $last = $x;
                $first = null;
                foreach ($next as $x) if ($first === null || $x['start'] < $first['start']) $first = $x;

                if ($first['start'] < $last['end']) {
                    $warnings[] = [
                        'kind' => 'round_order',
                        'message' => "{$first['phase']} empieza ({$this->timeLabel($first['start'])}) antes de que termine {$last['phase']} ({$this->timeLabel($last['end'])}).",
                        'matches' => [$this->info($last['id'], false), $this->info($first['id'], false)],
                    ];
                }
            }

            $report[] = [
                'category_id' => $cid,
                'category' => $matches[0]['category'],
                'scheduled' => count($scheduled),
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        // Problems first, then by name.
        usort($report, fn($x, $y) => [count($x['errors']) === 0, count($x['warnings']) === 0, $x['category']]
            <=> [count($y['errors']) === 0, count($y['warnings']) === 0, $y['category']]);
        return $report;
    }

    /**
     * What a match needs finished before it can start, per side:
     *   ['match'=>id, 'source'=>'winner'|'loser']  — a feeder match;
     *   ['group'=>gid, 'letter'=>'A']              — a whole group (seed label).
     * Unscheduled BYE feeders are transparent: we look through them to what
     * THEY depend on (a "A1 vs BYE" slot really just waits on group A).
     */
    private function dependencies(array $m, array $letters, int $depth = 0): array
    {
        $deps = [];
        foreach (['a', 'b'] as $side) {
            $f = $m['feeders'][$side] ?? null;
            if ($f) {
                $feeder = $this->rows[$f['id']] ?? null;
                if (! $feeder) continue;
                if ($feeder['is_bye'] && $feeder['start'] === null && ! $feeder['confirmed'] && $depth < 8) {
                    foreach ($this->dependencies($feeder, $letters, $depth + 1) as $d) {
                        $deps[] = isset($d['match']) ? ['match' => $d['match'], 'source' => $f['source']] : $d;
                    }
                    continue;
                }
                $deps[] = ['match' => $f['id'], 'source' => $f['source']];
                continue;
            }

            if ($m['group_id'] !== null) continue; // group matches have no label deps
            $label = $m['seed_labels'][$side] ?? null;
            if (! $label || $label === 'BYE') continue;

            if (preg_match('/^Q\d+$/', $label)) {
                foreach ($letters as $L => $gid) $deps[] = ['group' => $gid, 'letter' => $L];
            } elseif (preg_match('/^([A-Z])\d+$/', $label, $mm) && isset($letters[$mm[1]])) {
                $deps[] = ['group' => $letters[$mm[1]], 'letter' => $mm[1]];
            }
        }

        // Unique.
        $u = [];
        foreach ($deps as $d) {
            $k = isset($d['match']) ? 'm' . $d['match'] : 'g' . $d['group'];
            $u[$k] ??= $d;
        }
        return array_values($u);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    private function info(int $mid, bool $possible): array
    {
        $m = $this->rows[$mid];
        return $m['info'] + ['id' => $mid, 'possible' => $possible, 'ts' => $m['start']];
    }

    private function dayKey(int $ts): string
    {
        return (new DateTimeImmutable('@' . $ts))->setTimezone($this->tz)->format('Y-m-d');
    }

    private function dayLabel(string $ymd): string
    {
        $d = new DateTimeImmutable($ymd, $this->tz);
        return self::DAYS[(int) $d->format('w')] . ' ' . $d->format('d') . ' ' . self::MONTHS[(int) $d->format('n')];
    }

    private function timeLabel(int $ts): string
    {
        $d = (new DateTimeImmutable('@' . $ts))->setTimezone($this->tz);
        return self::DAYS[(int) $d->format('w')] . ' ' . $d->format('H:i');
    }
}
