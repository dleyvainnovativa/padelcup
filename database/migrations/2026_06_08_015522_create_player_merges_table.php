<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for player identity merges. When a duplicate player is merged
 * into a canonical one, we record it so global ranking and history stay
 * explainable and (if needed) reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_merges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canonical_player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('merged_player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot')->nullable(); // pre-merge state of the merged record
            $table->timestamps();

            $table->index('canonical_player_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_merges');
    }
};
