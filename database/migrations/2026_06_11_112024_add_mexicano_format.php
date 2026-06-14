<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mexicano support:
 *  - categories.group_format: how 4-pair groups resolve (round_robin | mexicano).
 *    3/5-pair groups always fall back to round-robin.
 *  - categories.mexicano_pairing: round-2 pairing (cross | classic).
 *  - game_matches.feeder_a_source / feeder_b_source: whether a fed slot takes
 *    the WINNER or LOSER of its feeder match (round-robin/bracket use winner;
 *    Mexicano round 2 also feeds losers forward).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('group_format')->default('round_robin')->after('format');
            $table->string('mexicano_pairing')->default('cross')->after('group_format');
        });

        Schema::table('game_matches', function (Blueprint $table) {
            $table->string('feeder_a_source')->nullable()->after('feeder_a_id'); // winner|loser
            $table->string('feeder_b_source')->nullable()->after('feeder_b_id');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['group_format', 'mexicano_pairing']);
        });
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn(['feeder_a_source', 'feeder_b_source']);
        });
    }
};
