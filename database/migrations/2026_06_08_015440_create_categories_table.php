<?php

use App\Enums\CategoryFormat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Category is a self-contained mini-tournament: its own format, groups,
 * standings, ranking, capacity and color identity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();

            $table->string('name'); // "5ta Femenil", "Mixtos Intermedios"

            // Competition format
            $table->string('format')->default(CategoryFormat::RoundRobin->value);

            // Group sizing preference (engine flexes to absorb remainders; 5 only when forced)
            $table->unsignedTinyInteger('preferred_group_size')->default(4); // 3 or 4

            // Hybrid: how many advance from each group into the bracket
            $table->unsignedTinyInteger('advance_per_group')->default(2);

            // Capacity (pairs). min = soft warning, max = hard block.
            $table->unsignedSmallInteger('min_pairs')->default(0);
            $table->unsignedSmallInteger('max_pairs')->nullable();

            // Pricing — base price the manager wants the PLAYER to pay, MXN centavos.
            // (Player price; platform fee + Stripe come out per the agreed split.)
            $table->unsignedInteger('price_centavos')->default(120000); // $1,200.00

            // Optional registration window override (else inherit tournament)
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();

            // Color identity — auto-assigned tint index 1..6
            $table->unsignedTinyInteger('tint')->default(1);

            // Bracket extras
            $table->boolean('has_third_place')->default(false);

            // WhatsApp group link (surfaced to confirmed players)
            $table->string('whatsapp_group_url')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tournament_id', 'format']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
