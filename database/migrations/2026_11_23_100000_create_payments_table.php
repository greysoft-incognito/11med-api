<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments (app handover §16): one gateway transaction, for one or more targets of one purpose.
 * The patient is severed on erasure and the record kept, since it is a money record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->string('reference', 40)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('purpose', 16);
            $table->string('channel', 16)->nullable();
            $table->string('status', 16);
            $table->bigInteger('amount_kobo');
            $table->text('authorization_url')->nullable();
            $table->string('access_code', 100)->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->timestampTz('checked_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
