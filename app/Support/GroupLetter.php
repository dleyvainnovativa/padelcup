<?php

namespace App\Support;

/**
 * Single source of truth for a group's letter, derived from its 0-based
 * position index. A..Z for the first 26 groups, then AA, AB, … (spreadsheet
 * style) so a category with many groups never shows junk ASCII the way a bare
 * chr(ord('A') + $i) does past 'Z'.
 *
 * NOTE: the bracket seed-label scheme (A1, B2, Q1 …) and the resolvers that
 * parse it are single-letter. Bracket-backed categories are therefore expected
 * to stay at 26 groups or fewer (see BRACKET_MAX); the UI warns past that. The
 * multi-letter output here keeps round-robin-only categories (no bracket) from
 * breaking when they legitimately exceed 26 groups.
 */
class GroupLetter
{
    /** Bracket seed labels are single-letter, so A..Z only when a bracket feeds from groups. */
    public const BRACKET_MAX = 26;

    /** 0 → "A", 25 → "Z", 26 → "AA", 27 → "AB", … */
    public static function at(int $index): string
    {
        $index = max(0, $index);
        $letter = '';
        $n = $index;
        do {
            $letter = chr(ord('A') + ($n % 26)) . $letter;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);

        return $letter;
    }

    /** Full display name for a group at a 0-based position, e.g. "Grupo C". */
    public static function name(int $index): string
    {
        return 'Grupo ' . self::at($index);
    }
}
