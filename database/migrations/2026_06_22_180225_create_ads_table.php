<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-owner (admin) advertising banners shown in a 16:9 carousel on public
 * tournament pages. Scope is either 'global' (shows on every tournament) or
 * 'tournament' (shows only on the linked tournament).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('image_path');
            $table->string('link_url')->nullable();
            $table->enum('scope', ['global', 'tournament'])->default('global');
            $table->foreignId('tournament_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamps();

            $table->index(['scope', 'is_active', 'sort_order']);
            $table->index(['tournament_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
