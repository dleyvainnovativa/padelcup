<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // How much court/venue detail the public surfaces show.
            // 'full' (default) preserves current behaviour for every existing row.
            $table->string('public_court_visibility', 20)
                ->default('full')
                ->after('play_end');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('public_court_visibility');
        });
    }
};
