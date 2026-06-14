<?php

use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per PLAYER charge (payment is per-player; a pair is "fully paid"
 * only when both player charges succeed). This is the Interpretation B model:
 *  - amount_centavos: what the player pays (player price)
 *  - platform_fee_centavos: YOUR cut (application_fee_amount)
 *  - the manager (connected account) bears Stripe's processing fee
 *
 * Linked to a registration (the pair's lifecycle record) and to the specific
 * player being paid for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();

            // Who is paying (the user who initiated the charge; nullable for quick-register)
            $table->foreignId('payer_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Connected account that receives the funds (denormalized for audit)
            $table->string('connected_account_id')->nullable();

            // Money (MXN centavos)
            $table->unsignedInteger('amount_centavos');             // player price
            $table->unsignedInteger('platform_fee_centavos');       // your cut

            // Stripe references
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->string('stripe_charge_id')->nullable();
            $table->string('stripe_refund_id')->nullable();

            $table->string('status')->default(PaymentStatus::Unpaid->value);

            // Refund tracking
            $table->unsignedInteger('refunded_centavos')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->json('meta')->nullable(); // raw bits we want to keep

            $table->timestamps();

            $table->index(['registration_id', 'status']);
            $table->index('player_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
