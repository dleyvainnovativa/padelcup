<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Positional seed labels for bracket matches (e.g. "A1", "B2") so a bracket can
 * be generated BEFORE group standings are final — the real pairs bind in later.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->string('seed_label_a', 8)->nullable()->after('pair_b_id');
            $table->string('seed_label_b', 8)->nullable()->after('seed_label_a');
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn(['seed_label_a', 'seed_label_b']);
        });
    }
};
