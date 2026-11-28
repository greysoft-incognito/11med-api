<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The second step of a sign-in: a short-lived token that a password opened and a code completes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('two_factor_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('client', 8);
            $table->uuid('install_id')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('used_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_challenges');
    }
};
