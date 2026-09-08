<?php

use App\Enums\CategoryPlayFormat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `play_format` to categories: Doubles (default, current behaviour) or
 * Singles (tennis-style, one player per competing unit).
 *
 * Every existing category is doubles — that's the column default, so the
 * backfill is implicit and lossless. No data migration needed.
 *
 * play_format is orthogonal to `format` (round_robin/elimination/hybrid):
 * `format` = competition shape, `play_format` = unit size. A singles category
 * can still be round-robin, hybrid, etc. — the engine is unit-agnostic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('play_format')
                ->default(CategoryPlayFormat::Doubles->value)
                ->after('format');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('play_format');
        });
    }
};
