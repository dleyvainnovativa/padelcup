<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_substitutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pair_id')->constrained()->cascadeOnDelete();
            $table->foreignId('old_player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('new_player_id')->constrained('players')->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['pair_id']);
            $table->index(['new_player_id']);
            $table->index(['old_player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_substitutions');
    }
};
