<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an optional cover image to ranking systems — mirrors
 * tournaments.cover_image_path. Used on the public ranking page and, later, as
 * the "Circuito" image.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ranking_systems', function (Blueprint $table) {
            $table->string('cover_image_path')->nullable()->after('owner_label');
        });
    }

    public function down(): void
    {
        Schema::table('ranking_systems', function (Blueprint $table) {
            $table->dropColumn('cover_image_path');
        });
    }
};
