<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ranking_system_tournament — pivot. A tournament can feed MULTIPLE ranking
 * systems (e.g. AVP + a regional table), and a system aggregates many
 * tournaments. Presence of a row here = "this tournament is ranking-eligible
 * for this system." No boolean on tournaments needed.
 *
 * finalized_at: set when the tournament's ranking points have been written to
 * the ledger for THIS system (Phase 3 finalize action). Null = eligible but not
 * yet computed. Lets one tournament be finalized for AVP but not yet for another
 * system, and gives the UI a clear "pending vs done" state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranking_system_tournament', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ranking_system_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['ranking_system_id', 'tournament_id'], 'rst_unique');
            $table->index('tournament_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranking_system_tournament');
    }
};
