<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Hidden from the public calendar, pantalla and public category list.
            // The admin dashboard always shows the category. Default false so
            // every existing category stays public.
            $table->boolean('public_hidden')->default(false)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('public_hidden');
        });
    }
};
