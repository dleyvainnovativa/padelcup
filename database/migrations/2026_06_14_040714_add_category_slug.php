<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Pretty category slugs for public URLs (/t/{tournament}/{category:slug}).
 * Slugs are unique WITHIN a tournament (two tournaments may both have
 * "5ta Femenil", but one tournament cannot have two).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        // Backfill existing categories with per-tournament-unique slugs.
        Category::withoutGlobalScopes()->orderBy('id')->get()->each(function ($c) {
            $base = Str::slug($c->name) ?: 'categoria';
            $slug = $base;
            $i = 1;
            while (Category::withoutGlobalScopes()
                ->where('tournament_id', $c->tournament_id)
                ->where('slug', $slug)
                ->where('id', '!=', $c->id)
                ->exists()
            ) {
                $slug = $base . '-' . $i++;
            }
            $c->slug = $slug;
            $c->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
