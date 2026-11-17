<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Caregivers (app spec §6.5): a patient's invitation to a phone number, and once accepted, the
 * account that may read the patient's schedule. Revoking soft deletes the row; erasing either
 * account removes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caregivers', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->string('phone', 16);
            $table->string('invite_code', 12);
            $table->string('status', 16);
            $table->foreignId('caregiver_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestampTz('invited_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['patient_id', 'invited_at']);
            $table->index(['caregiver_user_id', 'status', 'accepted_at']);
        });

        DB::statement('CREATE UNIQUE INDEX caregivers_code_live_unique ON caregivers (invite_code) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX caregivers_phone_live_unique ON caregivers (patient_id, phone) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('caregivers');
    }
};
