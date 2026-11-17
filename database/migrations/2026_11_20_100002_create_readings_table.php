<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Home readings (app `zReading`, handover §14): blood pressure, blood sugar in mmol/L, weight. The
 * columns a type does not use stay null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readings', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 16);
            $table->unsignedSmallInteger('systolic')->nullable();
            $table->unsignedSmallInteger('diastolic')->nullable();
            $table->unsignedSmallInteger('pulse')->nullable();
            $table->decimal('mmol_l', 5, 2)->nullable();
            $table->string('context', 16)->nullable();
            $table->decimal('kg', 6, 2)->nullable();
            $table->timestampTz('taken_at');
            $table->string('note', 200)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['user_id', 'taken_at']);
            $table->index(['user_id', 'type', 'taken_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readings');
    }
};
