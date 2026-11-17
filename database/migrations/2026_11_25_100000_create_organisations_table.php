<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pharmacies and laboratories (portal handover §6 "Partners"): the application, and once
 * approved, the organisation its portal accounts belong to. Kind-specific settings live in the
 * Pharmacy and Laboratories modules' own tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->string('kind', 16);
            $table->string('name', 120);
            $table->string('state', 40);
            $table->string('city', 80)->nullable();
            $table->string('address', 200)->nullable();
            $table->string('licence_number', 40);
            $table->foreignId('licence_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('contact_name', 80);
            $table->string('contact_phone', 16);
            $table->string('contact_email', 255);
            $table->string('status', 16);
            $table->string('notes', 500)->nullable();
            $table->timestampTz('applied_at');
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['kind', 'status', 'applied_at']);
            $table->index(['status', 'applied_at']);
            $table->index('state');
        });

        DB::statement('CREATE UNIQUE INDEX organisations_licence_live_unique ON organisations (lower(licence_number)) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};
