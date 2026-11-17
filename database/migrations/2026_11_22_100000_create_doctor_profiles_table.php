<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A doctor's own record (app `zDoctorProfile`, concept §4): professional fields, fees, session
 * lengths, payout account, weekly hours, the live availability toggle, the review and the running
 * rating the directory sorts on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_profiles', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mdcn_number', 32)->nullable()->index();
            $table->string('specialty', 32)->nullable()->index();
            $table->string('sub_specialty', 120)->nullable();
            $table->unsignedSmallInteger('years_of_experience')->nullable();
            $table->string('current_institution', 160)->nullable();
            $table->jsonb('languages');
            $table->text('bio')->nullable();
            $table->foreignId('photo_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->bigInteger('fee_chat_kobo')->nullable();
            $table->bigInteger('fee_audio_kobo')->nullable();
            $table->bigInteger('fee_video_kobo')->nullable();
            $table->unsignedSmallInteger('session_minutes')->default(30);
            $table->unsignedSmallInteger('chat_window_hours')->default(24);
            $table->bigInteger('follow_up_fee_kobo')->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->text('bank_account_number')->nullable();
            $table->string('bank_account_last4', 4)->nullable();
            $table->jsonb('availability');
            $table->boolean('is_available_now')->default(false);
            $table->string('verification_status', 16)->default('pending');
            $table->text('verification_notes')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->unsignedInteger('rating_total')->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->decimal('rating_mean', 3, 2)->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['verification_status', 'submitted_at']);
            $table->index(['verification_status', 'is_available_now', 'rating_mean']);
        });

        DB::statement('CREATE UNIQUE INDEX doctor_profiles_user_live_unique ON doctor_profiles (user_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX doctor_profiles_languages_gin ON doctor_profiles USING gin (languages)');
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_profiles');
    }
};
