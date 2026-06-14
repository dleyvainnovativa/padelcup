<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Availability windows per court. The scheduler may only place matches
 * inside these windows (hard constraint). A court with no windows is
 * treated as always available within tournament dates.
 *
 * Modeled as concrete datetime windows (not weekday rules) to keep the
 * conflict math simple and to support irregular schedules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at')->nullable(); // UTC
            $table->timestamp('ends_at')->nullable();    // UTC
            $table->timestamps();

            $table->index(['court_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_availabilities');
    }
};
