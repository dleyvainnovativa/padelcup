<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a tournament appears in the public directory (Phase 8 browse-all).
 * Default false so nothing is exposed publicly until the manager opts in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->boolean('is_listed')->default(false)->after('phase');
            $table->index('is_listed');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropIndex(['is_listed']);
            $table->dropColumn('is_listed');
        });
    }
};
