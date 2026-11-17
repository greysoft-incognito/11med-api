<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Emailed links that set a portal password: a reset (an hour) or a first password (a week).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('kind', 8);
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_links');
    }
};
