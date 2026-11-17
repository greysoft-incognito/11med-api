<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A user's notification history (app `zNotification`), newest first. A dedupe key is written once
 * per user, so a repeated event never produces a second row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 32);
            $table->string('title', 200);
            $table->text('body');
            $table->uuid('target_id')->nullable();
            $table->string('dedupe_key', 191)->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['user_id', 'created_at', 'id']);
        });

        DB::statement('CREATE UNIQUE INDEX app_notifications_dedupe_live_unique ON app_notifications (user_id, dedupe_key) WHERE dedupe_key IS NOT NULL AND deleted_at IS NULL');
        DB::statement('CREATE INDEX app_notifications_unread_index ON app_notifications (user_id) WHERE read_at IS NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
