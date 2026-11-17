<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tests a doctor asked for from a consultation (app `zLabRequest`): a recommendation the patient
 * books at a laboratory of their choice. The doctor's name and MDCN number are kept as signed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_requests', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('consultation_id');
            $table->string('doctor_name');
            $table->string('mdcn_number', 32);
            $table->jsonb('tests');
            $table->string('notes', 500)->nullable();
            $table->timestampTz('issued_at');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['patient_id', 'issued_at']);
            $table->index('doctor_id');
        });

        DB::statement('CREATE UNIQUE INDEX lab_requests_consultation_live_unique ON lab_requests (consultation_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_requests');
    }
};
