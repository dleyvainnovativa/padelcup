<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-window scheduling (Step 2).
 *
 *  - tournament_phase_windows: reserved date/time ranges per phase
 *    (groups, final, semifinal, quarterfinal, r16, r32). The auto-scheduler
 *    places each match only within its phase's window(s).
 *  - tournaments.min_rest_minutes: minimum rest a pair must have between two
 *    of its matches (anti-collapse spacing).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tournament_phase_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('phase'); // groups | final | semifinal | quarterfinal | r16 | r32
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();

            $table->index(['tournament_id', 'phase']);
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_rest_minutes')->default(30)->after('match_duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_phase_windows');
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('min_rest_minutes');
        });
    }
};
