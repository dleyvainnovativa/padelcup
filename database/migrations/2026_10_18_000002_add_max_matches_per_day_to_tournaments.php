<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tournament daily match limit per player. Nullable: null means "use the
 * default of 2". Drives auto-scheduling, the live placement warnings, and the
 * "3+ en un día" audit threshold (threshold = limit + 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_matches_per_day')->nullable()->after('min_rest_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('max_matches_per_day');
        });
    }
};
