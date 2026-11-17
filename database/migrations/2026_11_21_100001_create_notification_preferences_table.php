<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-category delivery choices (app `zNotificationPreference`). A category with no row is on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 32);
            $table->boolean('push');
            $table->boolean('sms');
            $table->boolean('in_app');
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement('CREATE UNIQUE INDEX notification_preferences_live_unique ON notification_preferences (user_id, category) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
