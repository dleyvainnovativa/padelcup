<?php

namespace App\Services\Registration;

use App\Enums\InvitationStatus;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationSource;
use App\Enums\RegistrationStatus;
use App\Models\Category;
use App\Models\Pair;
use App\Models\PairInvitation;
use App\Models\Player;
use App\Models\Registration;
use App\Models\User;
use App\Services\Identity\PlayerMergeService;
use App\Services\Payment\StripeConnectService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Self-registration. Players register themselves; payment is mandatory before
 * the slot is valid. A pending-payment registration HOLDS a capacity slot with
 * a TTL (released by the ExpireInvitations command on timeout).
 *
 * Three partner flows, all built on one PairInvitation mechanism:
 *   1. payBoth     — registering player pays for both; partner added by name.
 *   2. inviteEmail — pay self, send a quick-register link (token) by email/share.
 *   3. (existing player target) — same link mechanism, pre-targeted.
 */
class SelfRegistrationService
{
    public function __construct(
        private StripeConnectService $stripe,
        private PlayerMergeService $players,
    ) {}

    /**
     * Begin a self-registration. Creates the pair (player2 maybe null), the
     * registration in pending_payment with a hold, and—unless paying for
     * both—a pending invitation. Returns charge client secrets to collect.
     *
     * @return array{registration: Registration, charges: array<int, array>}
     */
    public function begin(
        Category $category,
        User $registrant,
        array $player1,
        ?array $player2,
        string $flow, // 'pay_both' | 'invite'
    ): array {
        $this->assertOpenForRegistration($category);
        $this->assertHasCapacity($category);

        return DB::transaction(function () use ($category, $registrant, $player1, $player2, $flow) {
            // Resolve the registering player from their account (or create).
            $p1 = $this->players->resolveOrCreateForUser($registrant);
            // Allow overriding display name/contact captured at registration.
            $p1->fill(array_filter([
                'name' => $player1['name'] ?? $p1->name,
                'phone' => $player1['phone'] ?? $p1->phone,
            ]))->save();

            $payBoth = $flow === 'pay_both';

            // Player 2 exists immediately only when paying for both.
            $p2 = null;
            if ($payBoth) {
                $p2 = Player::create([
                    'name' => $player2['name'],
                    'email' => $player2['email'] ?? null,
                    'phone' => $player2['phone'] ?? null,
                    'created_by' => $registrant->id,
                ]);
            }

            $pair = Pair::create([
                'category_id' => $category->id,
                'player1_id' => $p1->id,
                'player2_id' => $p2?->id,
                'schedule_preferences' => $player1['schedule_preferences'] ?? null,
            ]);

            $ttlHours = $category->tournament->invitation_ttl_hours ?: 48;

            $registration = Registration::create([
                'pair_id' => $pair->id,
                'category_id' => $category->id,
                'source' => RegistrationSource::Self_,
                'status' => RegistrationStatus::PendingPayment,
                'payment_status' => PaymentStatus::Unpaid,
                'hold_expires_at' => now()->addHours($ttlHours),
                'terms_accepted_at' => now(),
                'terms_version' => config('app.terms_version', '1.0'),
            ]);

            // Build the charges to collect now.
            $charges = [];

            if ($payBoth) {
                // ONE combined Checkout covering both players (two line items).
                $result = $this->stripe->createPairCharge($registration, $p1, $p2, $registrant);
                $charges[] = ['checkout_url' => $result['checkout_url']];
            } else {
                // Registrant pays their half now; partner completes via invite.
                $charges[] = $this->stripe->createPlayerCharge($registration, $p1, $registrant);

                PairInvitation::create([
                    'pair_id' => $pair->id,
                    'registration_id' => $registration->id,
                    'invited_by_user_id' => $registrant->id,
                    'invitee_email' => $player2['email'] ?? null,
                    'expires_at' => now()->addHours($ttlHours),
                ]);
            }

            return ['registration' => $registration, 'charges' => $charges];
        });
    }

    /**
     * Partner accepts an invitation (quick-register link). Creates/links the
     * partner player, attaches to the pair, returns the charge to collect.
     *
     * @return array{registration: Registration, charge: array}
     */
    public function acceptInvitation(PairInvitation $invitation, array $partner): array
    {
        if (! $invitation->isPending() || $invitation->isExpired()) {
            throw ValidationException::withMessages([
                'invitation' => 'Esta invitación ya no es válida o expiró.',
            ]);
        }

        return DB::transaction(function () use ($invitation, $partner) {
            $pair = $invitation->pair;

            // Create or link the partner player (quick-register: no account).
            $p2 = ! empty($partner['player_id'])
                ? Player::findOrFail($partner['player_id'])
                : Player::create([
                    'name' => $partner['name'],
                    'email' => $partner['email'] ?? null,
                    'phone' => $partner['phone'] ?? null,
                ]);

            $pair->update(['player2_id' => $p2->id]);

            // Charge the partner's half.
            $charge = $this->stripe->createPlayerCharge($invitation->registration, $p2);

            return ['registration' => $invitation->registration, 'charge' => $charge];
        });
    }

    /** Mark an invitation accepted once the partner's payment settles. */
    public function markInvitationAccepted(PairInvitation $invitation): void
    {
        $invitation->update([
            'status' => InvitationStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }

    // --- Guards --------------------------------------------------------

    public function assertOpenForRegistration(Category $category): void
    {
        $opens = $category->registration_opens_at ?? $category->tournament->registration_opens_at;
        $closes = $category->registration_closes_at ?? $category->tournament->registration_closes_at;
        $now = now();

        if ($opens && $now->lt($opens)) {
            throw ValidationException::withMessages(['registration' => 'La inscripción aún no abre.']);
        }
        if ($closes && $now->gt($closes)) {
            throw ValidationException::withMessages(['registration' => 'La inscripción ya cerró.']);
        }
    }

    public function assertHasCapacity(Category $category): void
    {
        if ($category->isFull()) {
            throw ValidationException::withMessages(['registration' => 'La categoría está llena.']);
        }
    }
}
