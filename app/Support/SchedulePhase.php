<?php

namespace App\Support;

/**
 * Central definition of scheduling phases (for phase-window scheduling).
 * Keys are stable strings stored in tournament_phase_windows.phase and
 * returned by GameMatch::phaseKey().
 */
class SchedulePhase
{
    /**
     * Ordered phase keys → Spanish labels. Order = chronological.
     *
     * Keys name the round by how many pairs it has left (r64 = round of 64 =
     * "treintaidosavos", r32 = round of 32 = "dieciseisavos", r16 = round of 16
     * = "octavos"). A 64-slot bracket has 6 bracket rounds, so r64 is required —
     * without it the first two rounds collapse onto the same phase and the
     * scheduler forces both into one window. Brackets of 32 or fewer never use
     * r64 and are unaffected by its addition.
     */
    public const LABELS = [
        'groups' => 'Grupos',
        'r64' => 'Treintaidosavos',
        'r32' => 'Dieciseisavos',
        'r16' => 'Octavos',
        'quarterfinal' => 'Cuartos de final',
        'semifinal' => 'Semifinal',
        'final' => 'Final',
    ];

    /** All phase keys in chronological order. */
    public static function keys(): array
    {
        return array_keys(self::LABELS);
    }

    public static function label(string $key): string
    {
        return self::LABELS[$key] ?? $key;
    }

    /**
     * Which phases actually exist for a tournament, based on its matches.
     * Returns ordered phase keys that have at least one match.
     *
     * @param  \Illuminate\Support\Collection<\App\Models\GameMatch>  $matches
     */
    public static function presentIn($matches): array
    {
        $present = $matches->map(fn($m) => $m->phaseKey())->unique()->all();
        // Preserve canonical order.
        return array_values(array_filter(self::keys(), fn($k) => in_array($k, $present, true)));
    }
}
