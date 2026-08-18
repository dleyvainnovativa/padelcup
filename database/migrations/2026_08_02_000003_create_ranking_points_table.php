<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ranking_points — the LEDGER. One row per (system, tournament, category,
 * player, achievement). Points are awarded to PLAYERS (both members of a pair
 * get a row), with pair_id kept for "who they played with" display.
 *
 * This table is append/rewrite, never incrementally mutated: the leaderboard is
 * SUM(points) GROUP BY player over this ledger, filtered by system. Re-finalizing
 * a tournament deletes its rows for that system and rewrites them, so an edited
 * result recomputes cleanly and totals never drift.
 *
 * awarded_at = when finalize wrote the row. category_id lets you show per-category
 * ranking splits later (Phase 5) without schema changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranking_points', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ranking_system_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();

            // Kept for display/traceability (which pair earned it). Nullable so a
            // player row survives even if the pair is later deleted.
            $table->foreignId('pair_id')->nullable()->constrained('pairs')->nullOnDelete();

            $table->string('achievement');   // RankingAchievement value
            $table->integer('points');       // snapshot of the points at finalize time

            $table->timestamp('awarded_at')->nullable();
            $table->timestamps();

            // One achievement per player per category per tournament per system.
            $table->unique(
                ['ranking_system_id', 'tournament_id', 'category_id', 'player_id', 'achievement'],
                'ranking_points_unique'
            );

            // Leaderboard query: sum by system + player.
            $table->index(['ranking_system_id', 'player_id']);
            // Finalize rewrite: delete by system + tournament.
            $table->index(['ranking_system_id', 'tournament_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranking_points');
    }
};
