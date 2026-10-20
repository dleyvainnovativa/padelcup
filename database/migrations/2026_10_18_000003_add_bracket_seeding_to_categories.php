<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-category bracket seeding mode for hybrid categories:
 *   'group'   (default) → seed by group position (A1, B1, …)
 *   'overall'           → seed by the overall cross-group ranking
 * Only the seed order changes; the qualifier count is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('bracket_seeding', 16)->default('group')->after('extra_qualifiers');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('bracket_seeding');
        });
    }
};
