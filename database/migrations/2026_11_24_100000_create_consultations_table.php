<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consultations (app spec §7): a patient's paid booking with a doctor, its two deadlines, the session
 * and the rating. Erasing the patient removes the
 * rows, except a completed one a doctor is owed for, which stays without them, the complaint or the chat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('patient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('doctor_name', 160);
            $table->string('doctor_specialty', 32);
            $table->string('session_type', 8);
            $table->string('status', 16);
            $table->text('complaint');
            $table->unsignedInteger('fee_kobo');
            $table->timestampTz('scheduled_for');
            $table->boolean('scheduled')->default(false);
            $table->unsignedInteger('length_minutes');
            $table->foreignId('follow_up_of_id')->nullable()->constrained('consultations')->nullOnDelete();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->boolean('profile_shared');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('review', 500)->nullable();
            $table->timestampTz('rated_at')->nullable();
            $table->string('call_provider', 16)->nullable();
            $table->string('call_room', 255)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['patient_id', 'created_at', 'id']);
            $table->index(['patient_id', 'doctor_id', 'status']);
            $table->index(['doctor_id', 'status', 'scheduled_for']);
            $table->index(['status', 'expires_at']);
            $table->index(['status', 'session_type', 'started_at']);
        });

        // The doctor's inbox reads what is waiting on them first (app mock `GET doctor/consultations`).
        DB::statement("ALTER TABLE consultations ADD COLUMN inbox_rank smallint GENERATED ALWAYS AS (CASE status WHEN 'pending' THEN 0 WHEN 'confirmed' THEN 1 WHEN 'in_progress' THEN 2 ELSE 9 END) STORED");
        DB::statement('CREATE INDEX consultations_inbox_index ON consultations (doctor_id, inbox_rank, created_at DESC, id DESC) WHERE paid_at IS NOT NULL');

        // A slot is held once: the second booking of the same instant fails here, not in a race.
        DB::statement("CREATE UNIQUE INDEX consultations_slot_held_unique ON consultations (doctor_id, scheduled_for) WHERE scheduled AND status IN ('pending', 'confirmed', 'in_progress') AND deleted_at IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
