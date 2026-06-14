<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Players are PARTICIPANT records, distinct from User login accounts.
 *  - email/phone are OPTIONAL (manager can create contactless players)
 *  - user_id links a player to a login account when claimed/merged
 *  - a User may link to several Player records (different identities over time);
 *    a Player links to at most one User.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Link to a login account (nullable — contactless players have none)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Who created this record (a manager), for scoping/dedupe context
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Soft identity helpers for fuzzy dedupe
            $table->string('normalized_name')->nullable()->index();

            $table->timestamps();
            $table->softDeletes(); // merges soft-delete the losing record

            $table->index('email');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
