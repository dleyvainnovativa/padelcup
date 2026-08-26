<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tournament tiebreak criteria order. JSON array of criterion keys, e.g.
 * ["matches_won","head_to_head","sets_won","games_won"]. Null = use the default
 * (App\Support\TiebreakCriteria::DEFAULT_ORDER). Standings are derived live, so
 * changing this re-sorts existing standings immediately — no data migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->json('tiebreak_order')->nullable()->after('day_hours');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('tiebreak_order');
        });
    }
};
