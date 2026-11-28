<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Copies of their data an app account asked for (NDPR right of access): built by a job, kept for
 * two days, then removed with the file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_exports', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16);
            $table->string('path')->nullable();
            $table->timestampTz('requested_at');
            $table->timestampTz('ready_at')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampsTz();

            $table->index(['user_id', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_exports');
    }
};
