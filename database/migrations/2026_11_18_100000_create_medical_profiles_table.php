<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A patient's medical profile (app spec §3): the required core, the progressive fields, the
 * emergency contact and the HMO. Lists the API never filters on are JSONB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_profiles', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date_of_birth');
            $table->string('gender', 24);
            $table->string('blood_type', 3);
            $table->string('genotype', 2);
            $table->string('state_of_residence', 40);
            $table->string('lga', 60)->nullable();
            $table->jsonb('drug_allergies');
            $table->string('contact_name', 120);
            $table->string('contact_phone', 16);
            $table->string('contact_relationship', 16);
            $table->string('contact_address', 255)->nullable();
            $table->timestampTz('contact_verified_at')->nullable();
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->decimal('weight_kg', 5, 1)->nullable();
            $table->foreignId('photo_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->jsonb('existing_conditions');
            $table->jsonb('past_surgeries');
            $table->jsonb('food_allergies');
            $table->text('allergy_notes')->nullable();
            $table->string('hmo_provider', 120)->nullable();
            $table->string('hmo_member_id', 64)->nullable();
            $table->string('hmo_plan_type', 64)->nullable();
            $table->date('hmo_expiry_date')->nullable()->index();
            $table->string('nhia_number', 64)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['state_of_residence', 'lga']);
        });

        DB::statement('CREATE UNIQUE INDEX medical_profiles_user_live_unique ON medical_profiles (user_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_profiles');
    }
};
