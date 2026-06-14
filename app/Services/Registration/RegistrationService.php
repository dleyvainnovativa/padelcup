<?php

namespace App\Services\Registration;

use App\Enums\PaymentStatus;
use App\Enums\RegistrationSource;
use App\Enums\RegistrationStatus;
use App\Models\Category;
use App\Models\Pair;
use App\Models\Player;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registration logic. Phase 2 covers the MANAGER path:
 *   - manager-created pairs are confirmed immediately
 *   - payment is tracked separately (pay-later allowed)
 *   - capacity (max_pairs) is enforced as a hard block
 *
 * The self-registration path (payment-gated, invitations, holds) is layered
 * on in Phase 4 — this service is where that branch will live too.
 */
class RegistrationService
{
    /**
     * Create a manager pair in a category from two player definitions.
     *
     * Each player def: ['name' => ..., 'email' => ?, 'phone' => ?, 'player_id' => ?]
     * If player_id is given, that existing Player is used; otherwise one is created.
     *
     * @throws ValidationException when the category is full.
     */
    public function createManagerPair(
        Category $category,
        array $player1,
        array $player2,
        User $manager,
        bool $markPaid = false,
    ): Pair {
        $this->assertHasCapacity($category);

        return DB::transaction(function () use ($category, $player1, $player2, $manager, $markPaid) {
            $p1 = $this->resolvePlayer($player1, $manager);
            $p2 = $this->resolvePlayer($player2, $manager);

            $pair = Pair::create([
                'category_id' => $category->id,
                'player1_id' => $p1->id,
                'player2_id' => $p2->id,
            ]);

            Registration::create([
                'pair_id' => $pair->id,
                'category_id' => $category->id,
                'source' => RegistrationSource::Manager,
                'status' => RegistrationStatus::Confirmed, // manager path: confirmed immediately
                'payment_status' => $markPaid ? PaymentStatus::Paid : PaymentStatus::Unpaid,
                'terms_accepted_at' => now(),
                'terms_version' => config('app.terms_version', '1.0'),
            ]);

            return $pair->load('player1', 'player2', 'registration');
        });
    }

    /** Resolve an existing player by id, or create a new one (manager-owned). */
    private function resolvePlayer(array $def, User $manager): Player
    {
        if (! empty($def['player_id'])) {
            return Player::findOrFail($def['player_id']);
        }

        return Player::create([
            'name' => $def['name'],
            'email' => $def['email'] ?? null,
            'phone' => $def['phone'] ?? null,
            'created_by' => $manager->id,
        ]);
    }

    /** Hard capacity check. min_pairs is a soft warning handled in the UI. */
    public function assertHasCapacity(Category $category): void
    {
        if ($category->isFull()) {
            throw ValidationException::withMessages([
                'category' => "La categoría «{$category->name}» está llena ({$category->max_pairs} parejas).",
            ]);
        }
    }

    /** Mark a registration's payment status (manager pay-later tracking). */
    public function setPaymentStatus(Registration $registration, PaymentStatus $status): Registration
    {
        $registration->update(['payment_status' => $status]);

        return $registration;
    }

    /** Remove a pair (and its registration) before the tournament locks. */
    public function removePair(Pair $pair): void
    {
        DB::transaction(function () use ($pair) {
            $pair->registration?->delete();
            $pair->delete();
        });
    }
}
