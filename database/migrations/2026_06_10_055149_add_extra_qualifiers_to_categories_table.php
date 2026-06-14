<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra qualifiers for hybrid (groups → knockout) categories.
 *
 * advance_per_group = guaranteed qualifiers per group (e.g. top 1 or 2).
 * extra_qualifiers  = additional pairs taken as the BEST finishers ranked
 *                     ACROSS all groups from the place immediately after the
 *                     auto-qualifying line — used to round the bracket to a
 *                     workable size (e.g. 5 group winners + 1 best runner-up = 6).
 *
 * When a cross-group tie on points can't be broken automatically, the manager
 * picks among the tied pairs manually (handled in the Phase 5 bracket engine).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedTinyInteger('extra_qualifiers')->default(0)->after('advance_per_group');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('extra_qualifiers');
        });
    }
};
