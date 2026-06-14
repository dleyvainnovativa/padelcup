<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * locked_at records when the tournament first locked (first confirmed result).
 * match_audits keeps a trail of result changes — who proposed/confirmed/edited
 * a score and the before/after — since editing a confirmed result affects
 * standings and bracket progression.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->timestamp('locked_at')->nullable()->after('phase');
        });

        Schema::create('match_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // proposed | confirmed | edited | reverted
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('game_match_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_audits');
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('locked_at');
        });
    }
};
