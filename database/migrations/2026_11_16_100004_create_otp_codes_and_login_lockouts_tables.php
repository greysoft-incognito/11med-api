<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-time codes (hashed, one live per target) and failed sign-ins per phone number.
 * Both are credentials, not records: pruned, never soft deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('target', 120);
            $table->char('code_hash', 64);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->boolean('resend')->default(false);
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();
            $table->index(['target', 'created_at']);
        });

        Schema::create('login_lockouts', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();
            $table->unsignedSmallInteger('failures')->default(0);
            $table->timestampTz('locked_until')->nullable();
            $table->timestampTz('hard_locked_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('login_lockouts');
    }
};
