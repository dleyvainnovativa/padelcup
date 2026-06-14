<?php

namespace App\Services\Identity;

use App\Models\Pair;
use App\Models\Player;
use App\Models\PlayerMerge;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Identity service: the Player/User split, duplicate detection, merging, and
 * claiming. This is a Phase-1 prerequisite for clean global ranking later —
 * duplicate player records would otherwise fragment a player's history.
 */
class PlayerMergeService
{
    /**
     * Find likely duplicates of a player (same normalized name, email, or phone).
     *
     * @return Collection<int, Player>
     */
    public function findDuplicates(Player $player): Collection
    {
        return Player::query()
            ->possibleDuplicatesOf($player)
            ->get();
    }

    /**
     * Merge $merged into $canonical: repoint all references, copy any missing
     * contact info to the canonical record, soft-delete the merged record,
     * and write an audit row. Wrapped in a transaction.
     */
    public function merge(Player $canonical, Player $merged, ?User $performedBy = null): Player
    {
        if ($canonical->is($merged)) {
            throw new \InvalidArgumentException('No se puede fusionar un jugador consigo mismo.');
        }

        return DB::transaction(function () use ($canonical, $merged, $performedBy) {
            $snapshot = $merged->only([
                'id',
                'name',
                'email',
                'phone',
                'user_id',
                'created_by',
            ]);

            // Repoint pair references (both slots).
            Pair::where('player1_id', $merged->id)->update(['player1_id' => $canonical->id]);
            Pair::where('player2_id', $merged->id)->update(['player2_id' => $canonical->id]);

            // Backfill missing contact info onto the canonical record.
            $fill = [];
            if (blank($canonical->email) && filled($merged->email)) {
                $fill['email'] = $merged->email;
            }
            if (blank($canonical->phone) && filled($merged->phone)) {
                $fill['phone'] = $merged->phone;
            }
            if (blank($canonical->user_id) && filled($merged->user_id)) {
                $fill['user_id'] = $merged->user_id;
            }
            if ($fill) {
                $canonical->fill($fill)->save();
            }

            // Audit + soft-delete the loser.
            PlayerMerge::create([
                'canonical_player_id' => $canonical->id,
                'merged_player_id' => $merged->id,
                'performed_by' => $performedBy?->id,
                'snapshot' => $snapshot,
            ]);

            $merged->delete(); // soft delete

            return $canonical->refresh();
        });
    }

    /**
     * Link a login account to a player record (the "claim" flow).
     * If the player is already linked to a different user, that's a conflict
     * the caller must resolve (e.g. via merge).
     */
    public function claim(Player $player, User $user): Player
    {
        if (filled($player->user_id) && $player->user_id !== $user->id) {
            throw new \RuntimeException('Este jugador ya está vinculado a otra cuenta.');
        }

        $player->forceFill(['user_id' => $user->id])->save();

        return $player;
    }

    /**
     * Resolve the canonical player for a user, creating one if none exists.
     * Used when a self-registering user has no linked player yet.
     */
    public function resolveOrCreateForUser(User $user): Player
    {
        $existing = Player::where('user_id', $user->id)->first();
        if ($existing) {
            return $existing;
        }

        return Player::create([
            'name' => $user->name,
            'email' => $user->email,
            'user_id' => $user->id,
        ]);
    }
}
