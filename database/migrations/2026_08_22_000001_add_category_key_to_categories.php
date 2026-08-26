<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Adds a cross-tournament `category_key` to categories: a normalized form of the
 * free-text category name (lowercase, accent-stripped, single-spaced — the same
 * treatment Player uses for normalized_name).
 *
 * This is what lets the ranking merge the SAME category typed slightly
 * differently across tournaments ("5ta Femenil" / "5TA FEMENIL " / "5ta femenil"
 * all → "5ta femenil"). Level 1 of the merge plan.
 *
 * The column is indexed for the leaderboard's GROUP BY. It is backfilled from the
 * existing name for every current category (derived purely from name → safe,
 * lossless). Level 2 (manual aliases for different SPELLINGS like "Quinta
 * Femenil") can later map onto this stable key without reprocessing the ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('category_key')->nullable()->after('slug')->index();
        });

        // Backfill using the SAME normalization the model will use going forward.
        // Chunk to stay memory-safe on large datasets; touch only the new column.
        Category::withTrashed()->select('id', 'name')->chunkById(500, function ($cats) {
            foreach ($cats as $cat) {
                DB::table('categories')
                    ->where('id', $cat->id)
                    ->update(['category_key' => $this->normalizeCategoryKeyForMigration($cat->name)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['category_key']);
            $table->dropColumn('category_key');
        });
    }
    private function normalizeCategoryKeyForMigration(?string $name): string
    {
        $name = Str::lower(trim((string) $name));
        $name = Str::ascii($name);
        return preg_replace('/\s+/', ' ', $name);
    }
};

/**
 * Standalone copy of the normalization for the backfill, so this migration
 * doesn't depend on the model method existing at migration time. Must match
 * Category::normalizeKey() exactly.
 */
