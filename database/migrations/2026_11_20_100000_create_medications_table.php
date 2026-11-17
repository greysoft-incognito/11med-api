<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medication schedules (app `zMedication`, spec §6.2). Doses are expanded from `specific_times` in
 * Africa/Lagos; `settled_through` is the instant up to which every dose has a row in `dose_logs`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('drug_name', 160);
            $table->string('brand_name', 160)->nullable();
            $table->string('dosage_strength', 60);
            $table->string('dosage_form', 16);
            $table->decimal('dose_quantity', 8, 2);
            $table->string('dose_unit', 16)->nullable();
            $table->string('frequency_type', 24);
            $table->jsonb('specific_times');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('with_food', 16);
            $table->string('special_instructions', 500)->nullable();
            $table->string('condition_being_treated', 160)->nullable();
            $table->boolean('reminder_active')->default(true);
            $table->boolean('caregiver_alerts')->default(false);
            $table->unsignedSmallInteger('refill_reminder_days')->nullable();
            $table->unsignedInteger('estimated_supply_units')->nullable();
            $table->timestampTz('paused_at')->nullable();
            $table->timestampTz('settled_through');
            $table->foreignId('prescribed_by_doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('prescription_id')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['user_id', 'created_at']);
            $table->index(['paused_at', 'settled_through']);
            $table->index('prescription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medications');
    }
};
