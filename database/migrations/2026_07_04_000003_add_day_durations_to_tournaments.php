<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-day match-duration overrides: {'Y-m-d' => minutes}. Absent days use the
 * tournament default (match_duration_minutes). Lets the last day run longer
 * for semifinals/finals.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->json('day_durations')->nullable()->after('match_duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('day_durations');
        });
    }
};
