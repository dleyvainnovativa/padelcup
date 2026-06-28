<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-player, per-tournament availability rules (manager-entered).
 * Keyed by NORMALIZED NAME (not player_id) because the same person is often a
 * separate Player record per category — keying by name makes one rule apply to
 * all of that person's pairs across categories, which is the intended behavior.
 *
 * One rule per (tournament, person, day): "available from `earliest_time`".
 * A day with no row = no constraint that day.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('player_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('normalized_name');     // matches Player::normalize()
            $table->date('day');                   // a specific play day
            $table->time('earliest_time');         // "available from" HH:MM
            $table->timestamps();

            $table->unique(['tournament_id', 'normalized_name', 'day'], 'player_avail_unique');
            $table->index(['tournament_id', 'normalized_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_availabilities');
    }
};
