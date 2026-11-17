<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A patient's booking at a laboratory (app `zLabBooking`, portal `zLaboratoryBooking`). Priced
 * from the laboratory's list when made and kept as booked; seen by the laboratory once `paid_at`
 * is set by the payment webhook. One live booking per doctor's request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_bookings', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->string('reference', 16)->unique();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('laboratory_id')->constrained('laboratories')->cascadeOnDelete();
            $table->foreignId('lab_request_id')->nullable()->constrained('lab_requests')->nullOnDelete();
            $table->jsonb('tests');
            $table->unsignedBigInteger('tests_kobo');
            $table->string('collection', 8);
            $table->unsignedBigInteger('collection_fee_kobo');
            $table->unsignedBigInteger('total_kobo');
            $table->jsonb('address')->nullable();
            $table->timestampTz('scheduled_for');
            $table->unsignedSmallInteger('fasting_hours');
            $table->string('status', 20);
            $table->timestampTz('booked_at');
            $table->timestampTz('paid_at')->nullable();
            $table->foreignId('results_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('results_name', 150)->nullable();
            $table->timestampTz('results_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancelled_by', 12)->nullable();
            $table->string('cancellation_reason', 200)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['patient_id', 'booked_at']);
            $table->index(['laboratory_id', 'status', 'scheduled_for']);
            $table->index(['laboratory_id', 'results_at']);
        });

        DB::statement('CREATE INDEX lab_bookings_tests_gin ON lab_bookings USING gin (tests jsonb_path_ops)');
        DB::statement("CREATE UNIQUE INDEX lab_bookings_request_live_unique ON lab_bookings (lab_request_id) WHERE lab_request_id IS NOT NULL AND status <> 'cancelled' AND deleted_at IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_bookings');
    }
};
