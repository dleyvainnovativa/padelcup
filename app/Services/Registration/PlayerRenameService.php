<?php

namespace App\Services\Registration;

use App\Models\Pair;
use App\Models\Player;
use App\Models\PlayerAvailability;
use App\Models\Tournament;
use Illuminate\Support\Facades\DB;

/**
 * Renaming a player is not just an UPDATE on players.name — several derived keys
 * hang off the name:
 *
 *  1. players.normalized_name — the identity key used by the SCHEDULER's conflict
 *     detection, the availability system, the cheatsheets, and public search.
 *     Player::booted() has a `saving` hook that re-derives this automatically, so
 *     saving the new name is enough for THIS column.
 *  2. player_availabilities.normalized_name — availability rules are stored per
 *     NAME (not player_id) in a separate table, so the hook does NOT touch them.
 *     A rename would orphan the player's rules and the scheduler would silently
 *     stop honouring them.
 *  3. pairs.display_name — when set, it OVERRIDES the derived "P1 / P2" label, so
 *     the old name would keep showing on the board and public pages.
 *
 * This service handles (2) and (3), which nothing else does.
 */
class PlayerRenameService
{
    /**
     * Rename a player. Returns the number of availability rules re-keyed.
     *
     * @param  Tournament|null  $tournament  scope for availability rules + display_name
     *                                       refresh. When null, availability rules are
     *                                       re-keyed across every tournament.
     */
    public function rename(Player $player, string $newName, ?Tournament $tournament = null): array
    {
        $newName = trim(preg_replace('/\s+/', ' ', $newName));
        $oldNormalized = $player->normalized_name ?: Player::normalize($player->name);
        $newNormalized = Player::normalize($newName);

        return DB::transaction(function () use ($player, $newName, $oldNormalized, $newNormalized, $tournament) {
            // 1. The player row. Player::booted() has a `saving` hook that keeps
            //    normalized_name in sync automatically, so setting `name` is enough.
            $player->update(['name' => $newName]);

            // 2. Availability rules are keyed by normalized_name — re-key them so
            //    the scheduler still finds this person's rules. (The saving hook
            //    does NOT do this; the rules live in another table.)
            $rulesMoved = 0;
            if ($oldNormalized !== $newNormalized) {
                $q = PlayerAvailability::where('normalized_name', $oldNormalized);
                if ($tournament) $q->where('tournament_id', $tournament->id);
                $rulesMoved = $q->update(['normalized_name' => $newNormalized]);
            }

            // 3. Any pair with a hard-coded display_name that included the OLD name
            //    would keep showing it. Clear it so the pair label re-derives from
            //    the (now correct) player names.
            $pairsQuery = Pair::where(fn ($q) => $q->where('player1_id', $player->id)->orWhere('player2_id', $player->id))
                ->whereNotNull('display_name');
            if ($tournament) {
                $pairsQuery->whereHas('category', fn ($q) => $q->where('tournament_id', $tournament->id));
            }
            $pairsCleared = $pairsQuery->update(['display_name' => null]);

            return [
                'rules_moved' => $rulesMoved,
                'pairs_cleared' => $pairsCleared,
                'normalized' => $newNormalized,
            ];
        });
    }
}
