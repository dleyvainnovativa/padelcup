<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Player profile claims.
 *
 * A player (login account, role=player) requests to own one or more
 * manager-created Player records that represent them. An admin validates,
 * because with no email/phone on player rows, NAME is the only signal —
 * admin approval is the sole guard against collision / impersonation.
 *
 *   player_claims       — one request, with status + admin review fields
 *   player_claim_items  — which Player rows the request covers (same human
 *                         often has one Player row per category)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // the claimer
            $table->string('status')->default('pending')->index();          // pending|approved|rejected
            $table->text('note')->nullable();                               // claimer's optional message
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();                        // admin's reason (esp. reject)
            $table->timestamps();
        });

        Schema::create('player_claim_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['player_claim_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_claim_items');
        Schema::dropIfExists('player_claims');
    }
};
