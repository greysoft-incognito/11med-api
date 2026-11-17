<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prescriptions (app spec §7.6): one per consultation, signed with the doctor's MDCN number, valid
 * thirty days, and applied to the patient's schedule only when the patient says so.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('consultation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('doctor_name', 160);
            $table->string('mdcn_number', 64);
            $table->text('diagnosis_summary');
            $table->jsonb('drugs');
            $table->timestampTz('issued_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('applied_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement('CREATE UNIQUE INDEX prescriptions_consultation_live_unique ON prescriptions (consultation_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
