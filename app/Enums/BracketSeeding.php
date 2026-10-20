<?php

namespace App\Enums;

/**
 * How a hybrid category's elimination bracket is seeded from the group phase:
 *   - Group:   by group position (A1, B1, C1, A2, …) — the classic default.
 *   - Overall: by the category's overall cross-group ranking (best finisher is
 *              seed 1), using the tournament's tiebreak criteria.
 *
 * Only the seed ORDER changes — the qualifier count (advance_per_group +
 * extra_qualifiers) is identical either way.
 */
enum BracketSeeding: string
{
    case Group = 'group';
    case Overall = 'overall';

    public function label(): string
    {
        return match ($this) {
            self::Group => 'Por posición de grupo',
            self::Overall => 'Por tabla general',
        };
    }
}
