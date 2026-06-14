<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');               // "Grupo A", "Grupo B"
            $table->unsignedSmallInteger('position')->default(0); // ordering
            $table->timestamps();

            $table->index(['category_id', 'position']);
        });

        Schema::create('group_pair', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pair_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['group_id', 'pair_id']);
            $table->index('pair_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_pair');
        Schema::dropIfExists('groups');
    }
};
