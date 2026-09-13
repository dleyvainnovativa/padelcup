<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Player-submitted score proposals, separate from the official match result.
 * A proposal never touches the match's own sets/winner — only the manager's
 * confirm/edit flow writes the official result. One PENDING proposal per match.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_match_id')->constrained('game_matches')->cascadeOnDelete();
            $table->foreignId('proposed_by')->constrained('users')->cascadeOnDelete();
            $table->json('sets');                    // [[a,b],[a,b],...]
            $table->unsignedBigInteger('winner_pair_id')->nullable(); // computed at propose time (display only)
            $table->string('status')->default('pending'); // pending | accepted | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['game_match_id', 'status']);
        });

        // Enforce "one pending proposal per match" at the DB level (portable:
        // a partial unique index isn't available on all drivers, so we guard in
        // code; this composite index keeps lookups fast).
    }

    public function down(): void
    {
        Schema::dropIfExists('match_proposals');
    }
};
