<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The platform's test catalogue (app spec §11.4). The code is the identity price lists,
 * requests and bookings point at, so it never changes once written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_tests', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->string('code', 16);
            $table->string('name', 80);
            $table->string('category', 16)->index();
            $table->string('sample', 16);
            $table->string('description', 300);
            $table->unsignedSmallInteger('fasting_hours');
            $table->unsignedSmallInteger('turnaround_hours');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('name');
        });

        DB::statement('CREATE UNIQUE INDEX lab_tests_code_live_unique ON lab_tests (code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_tests');
    }
};
