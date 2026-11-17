<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per app install that can receive push; the install moves to whoever registered it last.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('install_id');
            $table->string('platform', 16);
            $table->string('push_provider', 16);
            $table->text('push_token');
            $table->string('app_version', 32);
            $table->string('locale', 16);
            $table->timestampTz('registered_at');
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement('CREATE UNIQUE INDEX devices_install_live_unique ON devices (install_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
