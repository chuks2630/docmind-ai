<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Email/password auth (Technical Architecture §9) — not part of the
            // OAuth-only schema in §6, added here rather than editing the original migration.
            $table->string('password_hash')->nullable()->after('email');

            // Guest-to-account migration key (Technical Architecture §9). Placeholder for
            // Sprint 0: stores the client-generated guest device UUID so the sync module
            // (a later sprint) has something to key the local-data migration off of.
            $table->string('guest_device_id')->nullable()->after('password_hash');

            // Account deletion (Technical Architecture §14): marks the account for its
            // scheduled hard-delete while access is revoked immediately.
            $table->softDeletes();

            $table->unique(['auth_provider', 'auth_provider_id']);
            $table->index('guest_device_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['auth_provider', 'auth_provider_id']);
            $table->dropIndex(['guest_device_id']);
            $table->dropSoftDeletes();
            $table->dropColumn(['password_hash', 'guest_device_id']);
        });
    }
};
