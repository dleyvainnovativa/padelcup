<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // One Stripe charge (combined pay-both) maps to TWO payment rows,
            // so the intent id can't be unique. Keep it indexed for lookups.
            $table->dropUnique('payments_stripe_payment_intent_id_unique');
            $table->index('stripe_payment_intent_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['stripe_payment_intent_id']);
            $table->unique('stripe_payment_intent_id');
        });
    }
};
