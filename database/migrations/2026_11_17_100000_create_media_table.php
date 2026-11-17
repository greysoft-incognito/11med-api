<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every uploaded file. `user_id` is null for a partner's licence, uploaded before there is an
 * account; `claimed_at` is set when a record takes the file, and unclaimed rows are pruned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('purpose', 32);
            $table->string('disk', 32);
            $table->string('path', 255);
            $table->string('name', 160);
            $table->string('content_type', 64);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('size');
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['user_id', 'purpose']);
            $table->index(['claimed_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
