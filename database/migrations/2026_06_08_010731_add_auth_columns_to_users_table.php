<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds auth-related columns to the default Laravel users table:
 *  - nullable password (social-only accounts have none)
 *  - provider / provider_id (Socialite link)
 *  - role (admin | manager | player)
 *  - terms consent (timestamp + version)
 *
 * Run AFTER the default create_users_table migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Social-only users have no password
            $table->string('password')->nullable()->change();

            $table->string('provider')->nullable()->after('password');
            $table->string('provider_id')->nullable()->after('provider');

            $table->string('role')->default('player')->after('provider_id'); // admin|manager|player

            $table->timestamp('terms_accepted_at')->nullable()->after('role');
            $table->string('terms_version')->nullable()->after('terms_accepted_at');

            $table->index(['provider', 'provider_id']);
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['provider', 'provider_id']);
            $table->dropIndex(['role']);
            $table->dropColumn(['provider', 'provider_id', 'role', 'terms_accepted_at', 'terms_version']);
            // Note: reverting password to NOT NULL omitted to avoid data loss.
        });
    }
};
