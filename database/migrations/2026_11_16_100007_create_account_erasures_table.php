<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * That an account was erased: its role, when, and the reason given. Nothing that names the person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_erasures', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->string('role', 16)->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestampTz('erased_at')->index();
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_erasures');
    }
};
