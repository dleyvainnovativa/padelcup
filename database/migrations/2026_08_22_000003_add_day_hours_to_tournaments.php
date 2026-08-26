<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-day schedule hours override. Parallels day_durations: a JSON map keyed by
 * date, each { start: 'HH:MM', end: 'HH:MM' }. Days without an entry fall back to
 * the tournament's global play_start / play_end.
 *
 *   { "2026-08-27": {"start":"17:00","end":"23:00"},
 *     "2026-08-28": {"start":"17:00","end":"23:00"} }
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->json('day_hours')->nullable()->after('day_durations');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('day_hours');
        });
    }
};
