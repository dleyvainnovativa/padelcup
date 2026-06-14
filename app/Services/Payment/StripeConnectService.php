<?php

namespace App\Services\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;

/**
 * Stripe Connect: manager onboarding (Express) and per-player charges.
 *
 * Money model (Interpretation B):
 *   - Player pays `amount_centavos` (the player price).
 *   - We take `platform_fee_centavos` via application_fee_amount (clean $50).
 *   - on_behalf_of = connected account → the MANAGER is settlement merchant,
 *     so the manager bears Stripe's processing fee, not us.
 *   - transfer_data[destination] routes funds to the manager's account.
 */
class StripeConnectService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('stripe.secret'));
    }

    // --- Onboarding ----------------------------------------------------

    /** Create an Express connected account for a manager (idempotent). */
    public function ensureAccount(User $manager): string
    {
        if ($manager->stripe_account_id) {
            return $manager->stripe_account_id;
        }

        $account = $this->stripe->accounts->create([
            'type' => 'express',
            'country' => 'MX',
            'email' => $manager->email,
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
            'business_type' => 'individual',
            'metadata' => ['user_id' => $manager->id],
        ]);

        $manager->forceFill(['stripe_account_id' => $account->id])->save();

        return $account->id;
    }

    /** Build the hosted onboarding link the manager visits to finish setup. */
    public function onboardingLink(User $manager): string
    {
        $accountId = $this->ensureAccount($manager);

        $link = $this->stripe->accountLinks->create([
            'account' => $accountId,
            'refresh_url' => config('stripe.connect.refresh_url'),
            'return_url' => config('stripe.connect.return_url'),
            'type' => 'account_onboarding',
        ]);

        return $link->url;
    }

    /** Pull the account status from Stripe and mirror it locally. */
    public function syncAccountStatus(User $manager): void
    {
        if (! $manager->stripe_account_id) {
            return;
        }

        $account = $this->stripe->accounts->retrieve($manager->stripe_account_id);

        $manager->forceFill([
            'stripe_charges_enabled' => (bool) $account->charges_enabled,
            'stripe_payouts_enabled' => (bool) $account->payouts_enabled,
            'stripe_onboarded_at' => $account->charges_enabled ? now() : null,
        ])->save();
    }

    // --- Charging ------------------------------------------------------

    /**
     * Create ONE Checkout Session covering BOTH players of a pair (the
     * "pay for both" flow). Two line items (one per player) so Stripe shows an
     * itemized receipt, but a single transaction / single PaymentIntent.
     *
     * Records two Payment rows (one per player) that share the same session,
     * so rollUpRegistration() still sees two paid players when it settles, and
     * refunds map cleanly to the single charge.
     *
     * @return array{payments: array<int, Payment>, checkout_url: string}
     */
    public function createPairCharge(
        Registration $registration,
        Player $player1,
        Player $player2,
        ?User $payer = null,
    ): array {
        $category = $registration->category;
        $manager = $category->tournament->manager;

        if (! $manager->stripe_charges_enabled) {
            throw new \RuntimeException('El organizador aún no puede recibir pagos.');
        }

        $amount = $category->price_centavos;          // per player
        $fee = $category->tournament->platform_fee_centavos; // per player
        $totalFee = $fee * 2;                          // platform fee for both

        return DB::transaction(function () use ($registration, $player1, $player2, $payer, $manager, $amount, $totalFee, $fee, $category) {
            // Two local rows (per-player accounting), both pending.
            $p1 = Payment::create([
                'registration_id' => $registration->id,
                'player_id' => $player1->id,
                'payer_user_id' => $payer?->id,
                'connected_account_id' => $manager->stripe_account_id,
                'amount_centavos' => $amount,
                'platform_fee_centavos' => $fee,
                'status' => PaymentStatus::Pending,
            ]);
            $p2 = Payment::create([
                'registration_id' => $registration->id,
                'player_id' => $player2->id,
                'payer_user_id' => $payer?->id,
                'connected_account_id' => $manager->stripe_account_id,
                'amount_centavos' => $amount,
                'platform_fee_centavos' => $fee,
                'status' => PaymentStatus::Pending,
            ]);

            $session = $this->stripe->checkout->sessions->create([
                'mode' => 'payment',
                'locale' => 'es',
                'payment_method_types' => ['card', 'oxxo'],
                'line_items' => [
                    [
                        'quantity' => 1,
                        'price_data' => [
                            'currency' => config('stripe.currency'),
                            'unit_amount' => $amount,
                            'product_data' => [
                                'name' => "Inscripción · {$category->name}",
                                'description' => $player1->name,
                            ],
                        ],
                    ],
                    [
                        'quantity' => 1,
                        'price_data' => [
                            'currency' => config('stripe.currency'),
                            'unit_amount' => $amount,
                            'product_data' => [
                                'name' => "Inscripción · {$category->name}",
                                'description' => $player2->name,
                            ],
                        ],
                    ],
                ],
                'payment_intent_data' => [
                    'application_fee_amount' => $totalFee,
                    'on_behalf_of' => $manager->stripe_account_id,
                    'transfer_data' => ['destination' => $manager->stripe_account_id],
                    'metadata' => [
                        'registration_id' => $registration->id,
                        'payment_ids' => "{$p1->id},{$p2->id}",
                    ],
                ],
                'metadata' => [
                    'registration_id' => $registration->id,
                    'payment_ids' => "{$p1->id},{$p2->id}",
                ],
                'success_url' => route('registration.confirmation', $registration) . '?paid=1',
                'cancel_url' => route('registration.confirmation', $registration),
                'customer_email' => $payer?->email ?: $player1->email,
            ]);

            foreach ([$p1, $p2] as $payment) {
                $payment->update([
                    'stripe_payment_intent_id' => $session->payment_intent,
                    'meta' => [
                        'checkout_session_id' => $session->id,
                        'checkout_url' => $session->url,
                    ],
                ]);
            }

            return ['payments' => [$p1, $p2], 'checkout_url' => $session->url];
        });
    }

    /**
     * Create a Stripe Checkout Session for ONE player's fee against the
     * manager's connected account. Returns [Payment, checkout_url] — redirect
     * the player to the URL; Stripe hosts the card/OXXO form.
     *
     * Same Interpretation B split: application_fee_amount = your cut,
     * on_behalf_of + transfer_data[destination] = manager is settlement
     * merchant (bears Stripe's processing fee) and receives the funds.
     *
     * @return array{payment: Payment, checkout_url: string}
     */
    public function createPlayerCharge(
        Registration $registration,
        Player $player,
        ?User $payer = null,
    ): array {
        $category = $registration->category;
        $manager = $category->tournament->manager;

        if (! $manager->stripe_charges_enabled) {
            throw new \RuntimeException('El organizador aún no puede recibir pagos.');
        }

        $amount = $category->price_centavos;
        $fee = $category->tournament->platform_fee_centavos;

        return DB::transaction(function () use ($registration, $player, $payer, $manager, $amount, $fee, $category) {
            // Local record first (status pending until the webhook confirms).
            $payment = Payment::create([
                'registration_id' => $registration->id,
                'player_id' => $player->id,
                'payer_user_id' => $payer?->id,
                'connected_account_id' => $manager->stripe_account_id,
                'amount_centavos' => $amount,
                'platform_fee_centavos' => $fee,
                'status' => PaymentStatus::Pending,
            ]);

            $session = $this->stripe->checkout->sessions->create([
                'mode' => 'payment',
                'locale' => 'es',
                // Allow card and OXXO (cash) — both common in Mexico.
                'payment_method_types' => ['card', 'oxxo'],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => config('stripe.currency'),
                        'unit_amount' => $amount,
                        'product_data' => [
                            'name' => "Inscripción · {$category->name}",
                            'description' => "{$player->name} · {$category->tournament->name}",
                        ],
                    ],
                ]],
                'payment_intent_data' => [
                    'application_fee_amount' => $fee,
                    'on_behalf_of' => $manager->stripe_account_id,
                    'transfer_data' => ['destination' => $manager->stripe_account_id],
                    'metadata' => [
                        'payment_id' => $payment->id,
                        'registration_id' => $registration->id,
                        'player_id' => $player->id,
                    ],
                ],
                'metadata' => [
                    'payment_id' => $payment->id,
                    'registration_id' => $registration->id,
                    'player_id' => $player->id,
                ],
                // Single charge (invite flow): straight to confirmation.
                'success_url' => route('registration.confirmation', $registration) . '?paid=1',
                'cancel_url' => route('registration.confirmation', $registration),
                'customer_email' => $player->email ?: ($payer?->email),
            ]);

            $payment->update([
                'stripe_payment_intent_id' => $session->payment_intent,
                'meta' => [
                    'checkout_session_id' => $session->id,
                    'checkout_url' => $session->url,
                ],
            ]);

            return ['payment' => $payment, 'checkout_url' => $session->url];
        });
    }

    public function stripe(): StripeClient
    {
        return $this->stripe;
    }
}
