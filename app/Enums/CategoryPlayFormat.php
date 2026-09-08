<?php

namespace App\Enums;

/**
 * How a competing unit (Pair) is composed within a category.
 *
 *   Doubles — the classic pádel pair: two players (player1 + player2).
 *   Singles — one player per unit (tennis-style). Modeled as a Pair with
 *             player2_id = null, reusing the whole pair-based engine
 *             (groups / brackets / standings / scheduling) unchanged.
 *
 * This is orthogonal to CategoryFormat (round-robin / elimination / hybrid),
 * which governs the competition SHAPE. play_format governs the UNIT SIZE.
 */
enum CategoryPlayFormat: string
{
    case Doubles = 'doubles';
    case Singles = 'singles';

    public function label(): string
    {
        return match ($this) {
            self::Doubles => 'Dobles',
            self::Singles => 'Singles',
        };
    }

    /** Players expected per competing unit — also the number of fees. */
    public function playersPerUnit(): int
    {
        return match ($this) {
            self::Doubles => 2,
            self::Singles => 1,
        };
    }

    public function isSingles(): bool
    {
        return $this === self::Singles;
    }
}
