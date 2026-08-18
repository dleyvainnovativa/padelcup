<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ranking_systems — a named, association-owned ranking with its own points
 * schedule. Multiple systems coexist (AVP, a regional league, PadelCup's own).
 *
 * Design notes:
 *   - scope is fixed to 'player' for now (points awarded to each player of a
 *     pair) per the agreed design, but stored as a column so a future system
 *     could differ without a migration.
 *   - stacking = 'cumulative' means a pair accrues points for EVERY achievement
 *     they hit (group + each round reached + finalist + champion). 'best_only'
 *     would award just the single highest. Default cumulative.
 *   - points is a JSON map { achievement_key => points }. Keys come from the
 *     RankingAchievement enum. JSON (not a child table) because categories are
 *     per-tournament named rows with no persistent identity to hang per-category
 *     multipliers on; if you later add global category LEVELS, we can promote
 *     this to a ranking_rules table without touching the ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranking_systems', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // "AVP Ranking 2026"
            $table->string('owner_label')->nullable();    // "AVP", "Liga Veracruz"
            $table->string('scope')->default('player');   // 'player' | (future) 'pair'
            $table->string('stacking')->default('cumulative'); // 'cumulative' | 'best_only'
            $table->json('points')->nullable();           // { group_stage: 10, ... }
            $table->boolean('is_active')->default(true);

            // Who created it (manager scoping), mirroring your other tables.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranking_systems');
    }
};
