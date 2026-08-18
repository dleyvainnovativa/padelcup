<?php

namespace App\Enums;

/**
 * Canonical ranking achievements. Each maps to a points value in a ranking
 * system's points schedule (RankingSystem::$points JSON). This enum is the
 * SINGLE source of truth for achievement keys — the points editor, the
 * detection logic (Phase 3), and the ledger all reference it.
 *
 * Order here is the natural progression (used for display ordering).
 *
 * IMPORTANT: these keys are stored in the ranking_points ledger. Renaming a
 * case later means a data migration, so treat the string values as stable.
 */
enum RankingAchievement: string
{
    case GroupStage = 'group_stage';   // participated in the group phase (>=1 group match)
    case GroupWin   = 'group_win';     // finished 1st in their group
    case ReachedR32 = 'reached_r32';   // appeared in a round-of-32 bracket match
    case ReachedR16 = 'reached_r16';   // appeared in a round-of-16 (octavos) match
    case ReachedQf  = 'reached_qf';    // appeared in a quarterfinal (cuartos) match
    case ReachedSf  = 'reached_sf';    // appeared in a semifinal match
    case Finalist   = 'finalist';      // appeared in the final (real opponent existed)
    case Champion   = 'champion';      // won the category (always counts, even bye-final)

    /** Human label (Spanish) for the points editor and summaries. */
    public function label(): string
    {
        return match ($this) {
            self::GroupStage => 'Fase de grupos',
            self::GroupWin   => 'Ganó su grupo',
            self::ReachedR32 => 'Llegó a 32avos',
            self::ReachedR16 => 'Llegó a octavos',
            self::ReachedQf  => 'Llegó a cuartos',
            self::ReachedSf  => 'Llegó a semifinal',
            self::Finalist   => 'Finalista',
            self::Champion   => 'Campeón',
        };
    }

    /**
     * Which bracketRoundName() value corresponds to "appeared in this round".
     * Group achievements return null (they're detected via standings, not the
     * bracket). Champion/Finalist are detected from the final match specifically,
     * so they also return null here.
     *
     * This maps to the phase keys your app already uses:
     *   r32, r16, quarterfinal, semifinal, final
     */
    public function bracketRoundKey(): ?string
    {
        return match ($this) {
            self::ReachedR32 => 'r32',
            self::ReachedR16 => 'r16',
            self::ReachedQf  => 'quarterfinal',
            self::ReachedSf  => 'semifinal',
            default          => null,
        };
    }

    /** Default placeholder points for the seeded "PadelCup" system. */
    public function defaultPoints(): int
    {
        return match ($this) {
            self::GroupStage => 10,
            self::GroupWin   => 25,
            self::ReachedR32 => 30,
            self::ReachedR16 => 40,
            self::ReachedQf  => 60,
            self::ReachedSf  => 90,
            self::Finalist   => 130,
            self::Champion   => 200,
        };
    }

    /** All achievements as [key => default points] — handy for seeding. */
    public static function defaultSchedule(): array
    {
        $out = [];
        foreach (self::cases() as $a) {
            $out[$a->value] = $a->defaultPoints();
        }
        return $out;
    }
}
