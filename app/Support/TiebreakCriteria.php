<?php

namespace App\Support;

/**
 * The tiebreak criteria a manager can order per tournament. Each maps to either
 * a numeric row field (compared descending) or the special head-to-head logic.
 *
 * Row fields come from StandingsService::blankRow():
 *   won, sets_for, set_diff, games_for, game_diff
 */
final class TiebreakCriteria
{
    /** key => ['label' => Spanish label, 'field' => row key | null (h2h)] */
    public const CRITERIA = [
        'matches_won'   => ['label' => 'Partidos ganados', 'field' => 'won'],
        'head_to_head'  => ['label' => 'Enfrentamiento directo', 'field' => null],
        'sets_won'      => ['label' => 'Sets ganados', 'field' => 'sets_for'],
        'set_diff'      => ['label' => 'Diferencia de sets', 'field' => 'set_diff'],
        'games_won'     => ['label' => 'Games ganados', 'field' => 'games_for'],
        'game_diff'     => ['label' => 'Diferencia de games', 'field' => 'game_diff'],
    ];

    /** Default order when a tournament hasn't customized it. */
    public const DEFAULT_ORDER = ['matches_won', 'head_to_head', 'sets_won', 'games_won'];

    /** All valid criterion keys. */
    public static function keys(): array
    {
        return array_keys(self::CRITERIA);
    }

    /** True if $key is a known criterion. */
    public static function isValid(string $key): bool
    {
        return isset(self::CRITERIA[$key]);
    }

    /** Row field for a numeric criterion, or null for head_to_head/unknown. */
    public static function field(string $key): ?string
    {
        return self::CRITERIA[$key]['field'] ?? null;
    }

    public static function label(string $key): string
    {
        return self::CRITERIA[$key]['label'] ?? $key;
    }

    /**
     * Sanitize a stored/submitted order: keep only valid keys, drop dupes, and if
     * nothing valid remains, fall back to the default.
     */
    public static function sanitize(?array $order): array
    {
        $clean = collect($order ?? [])
            ->filter(fn ($k) => is_string($k) && self::isValid($k))
            ->unique()
            ->values()
            ->all();

        return $clean ?: self::DEFAULT_ORDER;
    }
}
