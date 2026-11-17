<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * App accounts sign in by phone with an optional email; portal accounts by email with no phone;
 * a social account may have no password. Phone and email are unique among live rows only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->string('full_name', 120)->nullable()->after('uuid');
            $table->string('username')->nullable()->change();
            $table->string('firstname')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->softDeletesTz();
            $table->dropUnique(['email']);
            $table->dropUnique(['phone']);
        });

        // The model sets a v7 uuid; the default covers rows written without model events.
        DB::statement('UPDATE users SET uuid = gen_random_uuid() WHERE uuid IS NULL');
        DB::statement('ALTER TABLE users ALTER COLUMN uuid SET DEFAULT gen_random_uuid(), ALTER COLUMN uuid SET NOT NULL');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_uuid_unique UNIQUE (uuid)');

        DB::statement('CREATE UNIQUE INDEX users_phone_live_unique ON users (phone) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX users_email_live_unique ON users (lower(email)) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_phone_live_unique');
        DB::statement('DROP INDEX IF EXISTS users_email_live_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['uuid', 'full_name', 'deleted_at']);
            $table->unique('email');
            $table->unique('phone');
        });
    }
};
