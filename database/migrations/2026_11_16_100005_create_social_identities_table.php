<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A Google or Apple identity bound to an account, confirmed once the phone code comes back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_identities', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 16);
            $table->string('subject', 255);
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement('CREATE UNIQUE INDEX social_identities_live_unique ON social_identities (provider, subject) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('social_identities');
    }
};
