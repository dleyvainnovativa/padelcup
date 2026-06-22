<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduling settings that drive the calendar grid:
 *   - play_start / play_end: daily play window (e.g. 08:00–22:00), applied to
 *     all courts (availability is derived from this, per decision A-i).
 *   - match_duration_minutes: slot length; grid rows step by this.
 * Stored as plain time strings (CDMX local; no DST).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->time('play_start')->default('08:00')->after('ends_on');
            $table->time('play_end')->default('23:00')->after('play_start');
            $table->unsignedSmallInteger('match_duration_minutes')->default(75)->after('play_end');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['play_start', 'play_end', 'match_duration_minutes']);
        });
    }
};
