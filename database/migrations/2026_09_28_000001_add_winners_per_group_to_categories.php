<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional per-group winner overrides for a hybrid category.
 *
 * `advance_per_group` stays the GLOBAL default (unchanged). This adds an
 * OPTIONAL map that overrides how many advance from specific groups, keyed by
 * group POSITION (0-based, matching groups.position ordering):
 *
 *   {"3": 2}   → group at position 3 advances 2, all others use the global.
 *
 * null/empty = current behaviour (global for every group). This lets a manager
 * handle an uneven bracket (e.g. a tied group of 4) without changing the global
 * count for the whole category.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->json('winners_per_group')->nullable()->after('advance_per_group');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('winners_per_group');
        });
    }
};
