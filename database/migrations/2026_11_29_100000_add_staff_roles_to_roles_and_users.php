<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff roles live in Spatie's `roles` table beside the five account roles, marked `is_staff`, with a
 * public id, a label the team edits and, for the seeded defaults, a fixed `key`. A team account is
 * deactivated by `deactivated_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique();
            $table->boolean('is_staff')->default(false)->index();
            $table->string('key', 32)->nullable()->unique();
            $table->string('label', 60)->nullable();
            $table->string('description', 300)->nullable();
        });

        DB::statement('CREATE UNIQUE INDEX roles_staff_label_unique ON roles (lower(label)) WHERE is_staff');

        Schema::table('users', function (Blueprint $table) {
            $table->timestampTz('deactivated_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deactivated_at');
        });

        DB::statement('DROP INDEX IF EXISTS roles_staff_label_unique');

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['uuid', 'is_staff', 'key', 'label', 'description']);
        });
    }
};
