<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a tournament opt out of global ads (e.g. when it's been exclusively sold
 * to one sponsor). Its own per-tournament ads still show.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->boolean('hide_global_ads')->default(false)->after('is_listed');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('hide_global_ads');
        });
    }
};
