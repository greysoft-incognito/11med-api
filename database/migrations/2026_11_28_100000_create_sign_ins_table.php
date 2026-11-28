<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per sign-in (refresh-token family): the device it came from and when it was last used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sign_ins', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('family')->unique();
            $table->string('client', 8);
            $table->uuid('install_id')->nullable();
            $table->string('device_key', 64)->nullable();
            $table->string('device_name', 120)->nullable();
            $table->string('platform', 16)->nullable();
            $table->timestampTz('signed_in_at');
            $table->timestampTz('last_used_at');
            $table->timestampTz('ended_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'ended_at']);
            $table->index(['user_id', 'device_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sign_ins');
    }
};
