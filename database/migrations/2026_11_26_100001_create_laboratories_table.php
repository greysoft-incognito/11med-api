<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A laboratory's listing beside its organisation (M11): hours, home collection, logo, cover and
 * rating. The name and address live on the organisation; its uuid is the laboratory's public id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratories', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('logo_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('opens', 5);
            $table->string('closes', 5);
            $table->unsignedBigInteger('home_collection_fee_kobo')->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement('CREATE UNIQUE INDEX laboratories_organisation_live_unique ON laboratories (organisation_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratories');
    }
};
