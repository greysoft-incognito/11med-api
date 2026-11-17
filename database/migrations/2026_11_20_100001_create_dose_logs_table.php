<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The answers to doses (app `zDose`): one live row per medication and scheduled instant, written by
 * the patient or, sixty minutes past due, by the missed-dose job. A retraction soft deletes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dose_logs', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('medication_id')->constrained('medications')->cascadeOnDelete();
            $table->timestampTz('scheduled_for');
            $table->string('state', 16);
            $table->string('skip_reason', 24)->nullable();
            $table->timestampTz('logged_at')->nullable();
            $table->unsignedSmallInteger('snooze_count')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['user_id', 'scheduled_for']);
            $table->index(['medication_id', 'scheduled_for']);
        });

        DB::statement('CREATE UNIQUE INDEX dose_logs_dose_live_unique ON dose_logs (medication_id, scheduled_for) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('dose_logs');
    }
};
