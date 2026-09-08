<?php

use App\Enums\CategoryPlayFormat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalizes "is this a singles unit?" onto the pair row.
 *
 * Why a column and not a category lookup: Pair::name() is called inside many
 * tight view/PDF loops. Reading play_format through $pair->category there would
 * either add an N+1 or force every caller to eager-load category. A local
 * boolean stamped at creation keeps name() query-free and makes the
 * PaymentReconciler's "how many fees?" check explicit and self-contained.
 *
 * The category still owns the authoritative play_format (used by the
 * registration UI and validation); this column is an immutable copy of "how
 * was this unit created", set once when the pair is inserted.
 *
 * Backfill: derive from each pair's category so any pairs already sitting in a
 * (newly-flagged) singles category are stamped correctly. For an all-doubles
 * dataset this is a no-op beyond the default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pairs', function (Blueprint $table) {
            $table->boolean('is_singles')->default(false)->after('category_id');
        });

        // Backfill from the owning category's play_format (default doubles).
        DB::table('pairs')
            ->join('categories', 'categories.id', '=', 'pairs.category_id')
            ->where('categories.play_format', CategoryPlayFormat::Singles->value)
            ->update(['pairs.is_singles' => true]);
    }

    public function down(): void
    {
        Schema::table('pairs', function (Blueprint $table) {
            $table->dropColumn('is_singles');
        });
    }
};
