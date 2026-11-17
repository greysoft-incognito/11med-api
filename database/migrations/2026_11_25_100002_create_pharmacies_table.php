<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A pharmacy's store settings (app `zPharmacy`, portal handover §4): its pictures and delivery
 * terms. One row per approved pharmacy organisation, made on approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('organisation_id')->unique()->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('logo_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->unsignedBigInteger('delivery_fee_kobo');
            $table->unsignedBigInteger('free_delivery_over_kobo')->nullable();
            $table->unsignedInteger('delivery_minutes')->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacies');
    }
};
