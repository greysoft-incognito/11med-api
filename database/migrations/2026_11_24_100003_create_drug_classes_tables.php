<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drug classes for the allergy cross-check (app handover §1.1): a class and the drug names in it, so
 * a penicillin allergy flags amoxicillin. A curated seed, to be reviewed by a clinician (HT-009).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drug_classes', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->string('code', 48);
            $table->string('name', 120);
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('drug_class_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_class_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->timestampsTz();

            $table->unique(['drug_class_id', 'name']);
            $table->index('name');
        });

        DB::statement('CREATE UNIQUE INDEX drug_classes_code_live_unique ON drug_classes (code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('drug_class_members');
        Schema::dropIfExists('drug_classes');
    }
};
