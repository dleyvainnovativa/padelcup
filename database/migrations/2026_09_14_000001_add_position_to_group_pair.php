<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes intra-group pair order authoritative.
 *
 * Until now the order of pairs inside a group was incidental (pivot insert
 * order). Two things read that order and it mattered silently:
 *   - buildMexicanoMatches() takes [$p1,$p2,$p3,$p4] = pairIds → decides R1
 *     pairings. So group order == Mexicano seeding, but no one could set it.
 *   - the group board / match listing render in that same order.
 *
 * A `position` column lets a manager drag-reorder pairs, which now genuinely
 * controls Mexicano R1 seeding and the match listing sequence.
 *
 * Backfill: stamp existing rows 0..n PER GROUP in current pivot-id order, so
 * nothing shifts on deploy — the order everyone already sees is preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_pair', function (Blueprint $table) {
            $table->unsignedSmallInteger('position')->default(0)->after('pair_id');
        });

        // Per-group 0-based backfill in existing (pivot id) order. Portable
        // across MySQL/MariaDB/SQLite — no window functions, no vendor SQL.
        $groupIds = DB::table('group_pair')->distinct()->pluck('group_id');
        foreach ($groupIds as $gid) {
            $rows = DB::table('group_pair')
                ->where('group_id', $gid)
                ->orderBy('id')
                ->pluck('id');
            foreach ($rows as $pos => $rowId) {
                DB::table('group_pair')->where('id', $rowId)->update(['position' => $pos]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('group_pair', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
