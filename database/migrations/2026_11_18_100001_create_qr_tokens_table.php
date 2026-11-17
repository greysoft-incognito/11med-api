<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Emergency QR tokens (app spec §3.3) and every open of one. A token is found by its SHA-256;
 * the token itself is kept encrypted so the owner's app can show the code again. Revoking
 * replaces it at once. An open records only when, never who.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_tokens', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->text('token');
            $table->timestampTz('issued_at');
            $table->unsignedInteger('opened_count')->default(0);
            $table->timestampTz('last_opened_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement('CREATE UNIQUE INDEX qr_tokens_user_current_unique ON qr_tokens (user_id) WHERE revoked_at IS NULL AND deleted_at IS NULL');

        Schema::create('qr_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_token_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('opened_at');

            $table->index(['qr_token_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_accesses');
        Schema::dropIfExists('qr_tokens');
    }
};
