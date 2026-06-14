<?php

use App\Enums\MatchState;
use App\Enums\MatchResultType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matches for both round-robin (within a group) and elimination (bracket).
 * Named game_matches because `match` is reserved in PHP.
 *
 * - group_id set for round-robin matches; null for bracket matches.
 * - round / slot describe bracket position; feeder_a/feeder_b reference the
 *   matches whose winners flow into this one (bracket progression).
 * - pairs may be null until a feeder resolves (bracket) — round-robin always
 *   has both pairs set at generation.
 * - sets stored as JSON array of [a, b] game counts; scoring is flexible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->cascadeOnDelete();

            // Participants (nullable for unresolved bracket slots)
            $table->foreignId('pair_a_id')->nullable()->constrained('pairs')->nullOnDelete();
            $table->foreignId('pair_b_id')->nullable()->constrained('pairs')->nullOnDelete();

            // Bracket structure
            $table->unsignedSmallInteger('round')->nullable();      // 1 = first round
            $table->unsignedSmallInteger('slot')->nullable();       // position within round
            $table->foreignId('feeder_a_id')->nullable()->constrained('game_matches')->nullOnDelete();
            $table->foreignId('feeder_b_id')->nullable()->constrained('game_matches')->nullOnDelete();
            $table->boolean('is_third_place')->default(false);

            // Scheduling (Phase 7 fills these; nullable here)
            $table->foreignId('court_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();

            // Result
            $table->string('state')->default(MatchState::Scheduled->value);
            $table->string('result_type')->default(MatchResultType::Normal->value);
            $table->json('sets')->nullable();                       // [[6,3],[6,4]]
            $table->foreignId('winner_pair_id')->nullable()->constrained('pairs')->nullOnDelete();
            $table->string('incident_note')->nullable();

            // Result workflow audit
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();

            $table->index(['category_id', 'state']);
            $table->index(['group_id']);
            $table->index(['category_id', 'round', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_matches');
    }
};
