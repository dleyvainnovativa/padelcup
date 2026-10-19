<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mesa de control timestamps. A match moves through:
 *   pendiente → llamado (called_at) → jugando (started_at) → terminado (ended_at)
 *
 * These are independent of the competition state (`state`/`winner_pair_id`):
 *   - called_at  : the mesa called the pair to court
 *   - started_at : the match physically started
 *   - ended_at   : the match physically finished (score may be captured later)
 *
 * All nullable; a match with none set is "pendiente". Clearing a later stamp
 * (stepping back) is allowed, so they are plain nullable timestamps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->timestamp('called_at')->nullable()->after('starts_at');
            $table->timestamp('started_at')->nullable()->after('called_at');
            $table->timestamp('ended_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn(['called_at', 'started_at', 'ended_at']);
        });
    }
};
