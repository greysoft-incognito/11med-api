<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A patient's premium as the team recorded it: one row per patient, premium through `premium_until`
 * (a Lagos calendar day). Erasing the patient removes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('premium_until');
            $table->string('reference', 64);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('premium_until');
            $table->index(['updated_at', 'id']);
        });

        DB::statement('CREATE UNIQUE INDEX subscriptions_user_live_unique ON subscriptions (user_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
