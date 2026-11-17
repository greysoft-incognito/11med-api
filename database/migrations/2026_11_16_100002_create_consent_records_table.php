<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consent as given: which policy version, when, and where. Kept after erasure, severed from the person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('context', 32)->index();
            $table->string('policy_version', 32);
            $table->timestampTz('accepted_at');
            $table->timestampTz('recorded_at');
            $table->nullableMorphs('subject');
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }
};
