<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ranking config v2 — per (system, tournament) overrides on the pivot:
 *
 *   points_override       JSON [achievement => points] used INSTEAD of the
 *                         system's default schedule when finalizing THIS
 *                         tournament for THIS system. Null = use the system
 *                         default. Everything still lands in the SAME ledger
 *                         under the same system_id, so the historical
 *                         leaderboard stays unified (item #5 — "alternate
 *                         point table, same history").
 *
 *   excluded_category_ids JSON array of category ids to skip when finalizing
 *                         this tournament for this system. Null/[] = include
 *                         every category (item #4 — toggle which categories
 *                         count).
 *
 * Both are nullable and default-free, so existing links keep their exact
 * behavior until a manager changes something.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ranking_system_tournament', function (Blueprint $table) {
            $table->json('points_override')->nullable()->after('finalized_at');
            $table->json('excluded_category_ids')->nullable()->after('points_override');
        });
    }

    public function down(): void
    {
        Schema::table('ranking_system_tournament', function (Blueprint $table) {
            $table->dropColumn(['points_override', 'excluded_category_ids']);
        });
    }
};
