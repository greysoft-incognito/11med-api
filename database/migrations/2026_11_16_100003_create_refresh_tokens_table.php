<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refresh tokens, stored as a SHA-256 of the secret. Each rotation adds a row to the family;
 * a row presented twice revokes the whole family.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('family')->index();
            $table->char('token_hash', 64)->unique();
            $table->foreignId('access_token_id')->nullable()->index()->constrained('personal_access_tokens')->nullOnDelete();
            $table->uuid('install_id')->nullable()->index();
            $table->string('client', 8);
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
