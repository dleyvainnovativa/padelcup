<?php

use App\Enums\ExpiryPolicy;
use App\Enums\TournamentPhase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();

            // Owning manager
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();

            // Branding / public page
            $table->string('name');
            $table->string('slug')->unique();           // public page slug
            $table->text('description')->nullable();
            $table->longText('rules')->nullable();       // rich text
            $table->string('logo_path')->nullable();

            // Dates (stored UTC; displayed CDMX)
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();

            // Registration window (tournament-level default; category may override later)
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();

            // Lifecycle
            $table->string('phase')->default(TournamentPhase::Setup->value);

            // Payment / expiry policy
            $table->unsignedInteger('invitation_ttl_hours')->default(48);
            $table->string('expiry_policy')->default(ExpiryPolicy::ManualReview->value);

            // Platform fee per player, in MXN centavos (your cut)
            $table->unsignedInteger('platform_fee_centavos')->default(5000); // $50.00

            // Optional billing flags (CFDI door open, not built)
            $table->boolean('iva_enabled')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['manager_id', 'phase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
