<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Pair is the competing unit within a Category. Two players.
 * The second player may be null while an invitation is pending
 * (self-registration: one player paid, partner not yet joined).
 *
 * NOTE: a player may appear in two pairs in the SAME category (common bad
 * practice in local pádel) — this is allowed; the scheduler enforces
 * player-level conflicts and group generation separates shared players.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            $table->foreignId('player1_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('player2_id')->nullable()->constrained('players')->nullOnDelete();

            // Display name override (else derived from the two players)
            $table->string('display_name')->nullable();

            // Seed for bracket placement (nullable = unseeded)
            $table->unsignedSmallInteger('seed')->nullable();

            // Per-pair schedule preferences: up to 3 ranked windows as JSON
            // e.g. [{"day":"mon-fri","from":"12:00","to":"15:00"}, ...]
            $table->json('schedule_preferences')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'player1_id']);
            $table->index('player2_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pairs');
    }
};
