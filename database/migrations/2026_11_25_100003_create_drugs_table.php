<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A pharmacy's medicines (`zDrug`). The NAFDAC number is unique on one pharmacy's live list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drugs', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('brand_name', 120)->nullable();
            $table->string('nafdac_number', 20);
            $table->string('category', 24);
            $table->string('dosage_form', 16);
            $table->string('dosage_strength', 60);
            $table->string('description', 600)->default('');
            $table->unsignedBigInteger('price_kobo');
            $table->boolean('requires_prescription');
            $table->boolean('in_stock');
            $table->foreignId('image_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['organisation_id', 'name']);
            $table->index(['category', 'in_stock']);
        });

        DB::statement('CREATE UNIQUE INDEX drugs_nafdac_live_unique ON drugs (organisation_id, lower(nafdac_number)) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('drugs');
    }
};
