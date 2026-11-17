<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A doctor's verification documents (concept §4.2): one row per kind once something is uploaded,
 * the file it points at, and the reviewer's decision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_documents', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('doctor_profile_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('status', 16);
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestampTz('uploaded_at');
            $table->string('rejection_reason', 300)->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement('CREATE UNIQUE INDEX doctor_documents_kind_live_unique ON doctor_documents (doctor_profile_id, kind) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_documents');
    }
};
