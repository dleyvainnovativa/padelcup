<?php

use App\Enums\PaymentStatus;
use App\Enums\RegistrationSource;
use App\Enums\RegistrationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Registration is the lifecycle record for a Pair entering a Category.
 * Separating it from `pairs` keeps the pair entity clean and lets the
 * registration carry source, status, payment summary, and consent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pair_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            $table->string('source')->default(RegistrationSource::Manager->value);
            $table->string('status')->default(RegistrationStatus::Confirmed->value);

            // Roll-up of per-player payments (detail lives in payments table, Phase 3)
            $table->string('payment_status')->default(PaymentStatus::Unpaid->value);

            // Slot hold expiry for pending_payment self-registrations
            $table->timestamp('hold_expires_at')->nullable();

            // Consent capture (single checkbox policy)
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'status']);
            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
