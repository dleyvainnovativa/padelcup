<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Score predictions ("quinielas"): a logged-in user guesses the exact set score
 * of a match before it locks (kickoff or confirmation). Scored 1 point for an
 * exact match, 0 otherwise, once the official result is confirmed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('game_match_id')->constrained('game_matches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('sets');                       // predicted [[a,b],...]
            $table->unsignedTinyInteger('points')->nullable(); // null until scored; 0 or 1 after
            $table->boolean('correct')->nullable();     // null until scored
            $table->timestamp('scored_at')->nullable();
            $table->timestamps();

            // One prediction per user per match (editable until lock).
            $table->unique(['game_match_id', 'user_id']);
            $table->index(['tournament_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_predictions');
    }
};
