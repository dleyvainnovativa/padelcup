<?php

namespace App\Services\Identity;

use App\Models\Player;
use App\Models\PlayerClaim;
use App\Models\User;
use App\Notifications\PlayerClaimReviewedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Player profile claiming. With no email/phone on player rows, NAME is the
 * only signal, so a claim is always admin-validated. See DESIGN.md.
 */
class PlayerClaimService
{
    public function __construct(private PlayerMergeService $merge) {}

    /**
     * Auto-link unclaimed player rows to a user by exact email match.
     *
     * Email is a strong identity signal (unlike name, which is why the claim
     * flow is manual), so when a manager-entered player email matches a user's
     * email we link directly — no admin review needed. Only touches rows that
     * are still unlinked (user_id null); never steals a player from another user.
     *
     * Safe to call repeatedly (idempotent). Returns the number of rows linked.
     * Call it both when a user is created and when a player gets an email.
     */
    public function autoLinkByEmail(User $user): int
    {
        $email = trim((string) $user->email);
        if ($email === '') return 0;

        return Player::whereNull('user_id')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->update(['user_id' => $user->id]);
    }

    /**
     * Search claimable Player records by name, grouped by "human".
     *
     * The same person has a separate Player row per category (dedupe only
     * happens on email/phone, which are absent here), so we group by
     * normalized_name + created_by (the owning manager) — matching how the
     * public player page resolves a human. Each group lists the tournaments /
     * categories / partners so the claimer (and later the admin) has context.
     *
     * Excludes rows already linked to a user, and rows already inside a
     * pending/approved claim.
     *
     * @return Collection<int, array> groups: [normalized_name, created_by,
     *         display_name, player_ids[], contexts[]]
     */
    public function search(string $name): Collection
    {
        $norm = Player::normalize($name);
        if ($norm === '' || mb_strlen($norm) < 2) {
            return collect();
        }

        // Player ids already spoken for (linked, or in a live claim).
        $lockedIds = $this->lockedPlayerIds();

        // $players = Player::query()
        //     ->whereNull('user_id')
        //     ->where('normalized_name', 'like', '%' . $norm . '%')
        //     ->whereNotIn('id', $lockedIds)
        //     ->with([
        //         'pairs.category.tournament',
        //         'pairs.player1',
        //         'pairs.player2',
        //     ])
        //     ->limit(200)
        //     ->get();
        $players = Player::query()
            ->whereNull('user_id')
            ->where('normalized_name', 'like', '%' . $norm . '%')
            ->whereNotIn('id', $lockedIds)
            ->with([
                'pairsAsPlayer1.category.tournament',
                'pairsAsPlayer1.player1',
                'pairsAsPlayer1.player2',

                'pairsAsPlayer2.category.tournament',
                'pairsAsPlayer2.player1',
                'pairsAsPlayer2.player2',
            ])
            ->limit(200)
            ->get();

        return $players
            ->groupBy(fn(Player $p) => $p->normalized_name . '|' . ($p->created_by ?? '0'))
            ->map(function (Collection $group) {
                $first = $group->first();

                $contexts = $group->flatMap(function (Player $p) {
                    return $p->pairs()->with('category.tournament')->get()->map(function ($pair) use ($p) {
                        $partner = $pair->player1_id === $p->id ? $pair->player2 : $pair->player1;
                        return [
                            'tournament' => $pair->category?->tournament?->name,
                            'category' => $pair->category?->name,
                            'partner' => $partner?->name,
                        ];
                    });
                })->filter(fn($c) => $c['tournament'])->unique(fn($c) => $c['tournament'] . $c['category'])->values();

                return [
                    'display_name' => $first->name,
                    'normalized_name' => $first->normalized_name,
                    'created_by' => $first->created_by,
                    'player_ids' => $group->pluck('id')->values()->all(),
                    'contexts' => $contexts->all(),
                ];
            })
            ->values();
    }

    /**
     * Submit a claim for a set of Player ids.
     *
     * @param  int[]  $playerIds
     * @throws ValidationException
     */
    public function submit(User $user, array $playerIds, ?string $note = null): PlayerClaim
    {
        $playerIds = array_values(array_unique(array_map('intval', $playerIds)));
        if (empty($playerIds)) {
            throw ValidationException::withMessages(['players' => 'Selecciona al menos un registro para reclamar.']);
        }

        $locked = $this->lockedPlayerIds();
        $conflicts = array_intersect($playerIds, $locked);
        if (! empty($conflicts)) {
            throw ValidationException::withMessages([
                'players' => 'Algunos registros ya fueron reclamados o están en revisión. Actualiza la búsqueda.',
            ]);
        }

        // The players must exist and be unlinked.
        $valid = Player::whereIn('id', $playerIds)->whereNull('user_id')->pluck('id')->all();
        if (count($valid) !== count($playerIds)) {
            throw ValidationException::withMessages(['players' => 'Uno o más registros ya no están disponibles.']);
        }

        return DB::transaction(function () use ($user, $playerIds, $note) {
            $claim = PlayerClaim::create([
                'user_id' => $user->id,
                'status' => PlayerClaim::PENDING,
                'note' => $note,
            ]);
            $claim->items()->createMany(array_map(fn($id) => ['player_id' => $id], $playerIds));

            return $claim;
        });
    }

    /**
     * Approve a claim: link every claimed Player to the claimer, all-or-nothing.
     * If any single link conflicts (already linked since submission), the whole
     * transaction rolls back so state stays consistent.
     */
    public function approve(PlayerClaim $claim, User $admin): PlayerClaim
    {
        if (! $claim->isPending()) {
            throw ValidationException::withMessages(['claim' => 'Esta solicitud ya fue revisada.']);
        }

        DB::transaction(function () use ($claim, $admin) {
            $claim->loadMissing('items.player', 'user');
            foreach ($claim->items as $item) {
                if (! $item->player) continue;
                // Reuses the existing guarded claim() (throws if linked elsewhere).
                $this->merge->claim($item->player, $claim->user);
            }
            $claim->update([
                'status' => PlayerClaim::APPROVED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);
        });

        $this->notify($claim->fresh('user'));

        return $claim;
    }

    /** Reject a claim with a reason. */
    public function reject(PlayerClaim $claim, User $admin, ?string $reason = null): PlayerClaim
    {
        if (! $claim->isPending()) {
            throw ValidationException::withMessages(['claim' => 'Esta solicitud ya fue revisada.']);
        }

        $claim->update([
            'status' => PlayerClaim::REJECTED,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'review_note' => $reason,
        ]);

        $this->notify($claim->fresh('user'));

        return $claim;
    }

    /** Player ids that can't be claimed: already linked, or in a live claim. */
    private function lockedPlayerIds(): array
    {
        $linked = Player::whereNotNull('user_id')->pluck('id');

        $inLiveClaim = DB::table('player_claim_items')
            ->join('player_claims', 'player_claims.id', '=', 'player_claim_items.player_claim_id')
            ->whereIn('player_claims.status', [PlayerClaim::PENDING, PlayerClaim::APPROVED])
            ->pluck('player_claim_items.player_id');

        return $linked->merge($inLiveClaim)->unique()->all();
    }

    private function notify(?PlayerClaim $claim): void
    {
        if ($claim && $claim->user) {
            $claim->user->notify(new PlayerClaimReviewedNotification($claim));
        }
    }
}