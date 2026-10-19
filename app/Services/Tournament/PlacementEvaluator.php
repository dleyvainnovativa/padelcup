<?php

namespace App\Services\Tournament;

/**
 * Pure, framework-free evaluation of "live placement warnings": the soft
 * problems with putting a match at a given time that the manager should see
 * before committing (they block-unless-forced, like conflicts). Kept pure so it
 * can be unit-mirrored without the DB — SchedulingService gathers the context
 * (feeders, source groups, per-day load) and hands it here.
 *
 * Context shape:
 *   [
 *     'startsTs'     => int,            // proposed start (unix seconds)
 *     'feeders'      => [ ['label'=>string, 'scheduled'=>bool, 'endsTs'=>?int], ... ],
 *     'sourceGroups' => [ ['name'=>string, 'endsTs'=>?int, 'unscheduled'=>int], ... ],
 *     'dayLoad'      => [ ['name'=>string, 'count'=>int], ... ],  // other matches that day
 *     'maxPerDay'    => ?int,           // null → no per-day limit check
 *   ]
 */
class PlacementEvaluator
{
    /** @return string[] warning lines (deduped) */
    public static function evaluate(array $ctx): array
    {
        $warnings = [];
        $start = $ctx['startsTs'] ?? null;

        // 1) Feeder dependencies (Mexicano R2, later bracket rounds).
        foreach ($ctx['feeders'] ?? [] as $f) {
            if (empty($f['scheduled'])) {
                $warnings[] = "Depende de un partido aún sin programar ({$f['label']}).";
            } elseif ($start !== null && ($f['endsTs'] ?? null) !== null && $start < $f['endsTs']) {
                $warnings[] = "Empieza antes de que termine el partido previo ({$f['label']}).";
            }
        }

        // 2) Source groups (bracket match seeded from group positions).
        foreach ($ctx['sourceGroups'] ?? [] as $g) {
            if (($g['unscheduled'] ?? 0) > 0) {
                $warnings[] = "El {$g['name']} tiene partidos sin programar.";
            } elseif ($start !== null && ($g['endsTs'] ?? null) !== null && $start < $g['endsTs']) {
                $warnings[] = "Empieza antes de que termine el {$g['name']}.";
            }
        }

        // 3) Per-day load for the placed match's known players.
        $max = $ctx['maxPerDay'] ?? null;
        if ($max !== null) {
            foreach ($ctx['dayLoad'] ?? [] as $pl) {
                $would = (int) ($pl['count'] ?? 0) + 1; // +1 for the match being placed
                if ($would > $max) {
                    $warnings[] = "{$pl['name']} tendría {$would} partidos ese día (máximo {$max}).";
                }
            }
        }

        return array_values(array_unique($warnings));
    }
}
