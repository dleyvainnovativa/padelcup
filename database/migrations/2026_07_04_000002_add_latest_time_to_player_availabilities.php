<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Availability becomes a WINDOW per day: available FROM earliest_time UNTIL
 * latest_time. latest_time is nullable — if absent, the rule behaves as before
 * (a floor with no upper bound), so existing rows keep working.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('player_availabilities', function (Blueprint $table) {
            $table->time('latest_time')->nullable()->after('earliest_time');
        });
    }

    public function down(): void
    {
        Schema::table('player_availabilities', function (Blueprint $table) {
            $table->dropColumn('latest_time');
        });
    }
};
