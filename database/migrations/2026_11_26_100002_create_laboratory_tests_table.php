<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A laboratory's price list: one row per catalogue test it offers, its price and whether it can
 * run it at the moment. Removing a test from the list soft deletes its row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratory_tests', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('laboratory_id')->constrained('laboratories')->cascadeOnDelete();
            $table->foreignId('lab_test_id')->constrained('lab_tests')->cascadeOnDelete();
            $table->unsignedBigInteger('price_kobo');
            $table->boolean('available');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['lab_test_id', 'available']);
        });

        DB::statement('CREATE UNIQUE INDEX laboratory_tests_live_unique ON laboratory_tests (laboratory_id, lab_test_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratory_tests');
    }
};
