<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every premium payment the team recorded, by its outside reference. A reference is used once.
 * Erasing the patient keeps the row without them, so the reference stays used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference', 64);
            $table->date('premium_until');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('recorded_at');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['user_id', 'recorded_at']);
        });

        DB::statement('CREATE UNIQUE INDEX subscription_payments_reference_live_unique ON subscription_payments (reference) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
