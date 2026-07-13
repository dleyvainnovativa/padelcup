<?php

namespace App\Services\Registration;

use App\Models\Pair;
use App\Models\Player;
use App\Models\PlayerSubstitution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replace one player of a pair with a DIFFERENT person (substitution), as opposed
 * to PlayerRenameService which fixes the spelling of the SAME person.
 *
 * Key differences from rename:
 *  - The incoming player is resolved/created via the normal dedup (email/phone),
 *    so an existing player (e.g. already in another category) is reused.
 *  - The outgoing player's AVAILABILITY rules are NOT transferred — they belonged
 *    to a different human. The incoming player's own rules (if any) apply.
 *  - A public PlayerSubstitution record is written so the player pages can show
 *    "reemplazó a …" / "reemplazado por …".
 *  - Allowed even after the tournament locks (injury sub), by design.
 *
 * Past matches still reference the PAIR, so the pair's displayed name updates to
 * the new player everywhere. (Level A: the substitution history lives on the
 * player profiles; we don't retro-relabel individual past matches here.)
 */
class PlayerSubstitutionService
{
    public function __construct(private RegistrationService $registration) {}

    /**
     * @param  Pair    $pair       the pair to modify
     * @param  int     $slot       1 or 2 (which player of the pair to replace)
     * @param  array   $incoming   ['name'=>, 'email'=>?, 'phone'=>?, 'player_id'=>?]
     * @param  User    $manager    acting manager (owner scope + audit)
     * @param  ?string $reason
     */
    public function substitute(Pair $pair, int $slot, array $incoming, User $manager, ?string $reason = null): PlayerSubstitution
    {
        if (! in_array($slot, [1, 2], true)) {
            throw ValidationException::withMessages(['slot' => 'Posición inválida.']);
        }

        $col = "player{$slot}_id";
        $oldPlayerId = $pair->{$col};
        if (! $oldPlayerId) {
            throw ValidationException::withMessages(['slot' => 'Esa posición no tiene jugador.']);
        }

        return DB::transaction(function () use ($pair, $slot, $col, $oldPlayerId, $incoming, $manager, $reason) {
            $oldPlayer = Player::findOrFail($oldPlayerId);

            // Resolve or create the incoming player (dedup by email/phone).
            $newPlayer = $this->registration->resolveOrCreatePlayer($incoming, $manager);

            if ($newPlayer->id === $oldPlayer->id) {
                throw ValidationException::withMessages([
                    'incoming' => 'El jugador entrante es el mismo que el saliente.',
                ]);
            }

            // Guard: the incoming player must not already be the OTHER member of
            // this same pair (would duplicate a person in one pair).
            $otherCol = $slot === 1 ? 'player2_id' : 'player1_id';
            if ($pair->{$otherCol} === $newPlayer->id) {
                throw ValidationException::withMessages([
                    'incoming' => 'Ese jugador ya es la otra mitad de esta pareja.',
                ]);
            }

            // Swap the player on the pair.
            $pair->{$col} = $newPlayer->id;
            // Clear a hard-coded display_name so the label re-derives to the new
            // player. (Availability rules are NOT transferred — different person.)
            $pair->display_name = null;
            $pair->save();

            // Public substitution record.
            return PlayerSubstitution::create([
                'tournament_id' => $pair->category->tournament_id,
                'category_id' => $pair->category_id,
                'pair_id' => $pair->id,
                'old_player_id' => $oldPlayer->id,
                'new_player_id' => $newPlayer->id,
                'reason' => $reason,
                'created_by' => $manager->id,
            ]);
        });
    }
}
