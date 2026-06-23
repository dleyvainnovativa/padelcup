<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend sponsors so ADMIN can add platform sponsors (global or per-tournament)
 * alongside the existing MANAGER-owned per-tournament sponsors.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('id');
            $table->string('scope')->default('tournament')->after('is_admin'); // global|tournament
        });

        Schema::table('sponsors', function (Blueprint $table) {
            $table->unsignedBigInteger('tournament_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'scope']);
        });
    }
};
