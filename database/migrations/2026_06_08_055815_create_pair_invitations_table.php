<?php

use App\Enums\InvitationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A pair invitation: the unifying mechanism behind all three self-registration
 * flows. The registering player creates a pair (player2 may be null) and an
 * invitation with a token + TTL. The partner accepts via:
 *   - quick-register link (no account): provides name/email + pays
 *   - existing player: accepts and pays
 * "Pay for both" auto-accepts on creation (no pending invitation needed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pair_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pair_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();

            // Who invited (the registering player's user, if any)
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Optional target: a known email, or an existing player to claim the slot
            $table->string('invitee_email')->nullable();
            $table->foreignId('target_player_id')->nullable()->constrained('players')->nullOnDelete();

            $table->string('token', 64)->unique();
            $table->string('status')->default(InvitationStatus::Pending->value);
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pair_invitations');
    }
};
