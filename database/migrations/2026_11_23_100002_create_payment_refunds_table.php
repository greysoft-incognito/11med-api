<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refunds asked of the gateway, one per cancelled share, settled by its webhook.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('payment_target_id')->constrained('payment_targets')->cascadeOnDelete();
            $table->bigInteger('amount_kobo');
            $table->string('status', 16);
            $table->string('gateway_refund_id', 64)->nullable();
            $table->timestampTz('requested_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['payment_id', 'status']);
            $table->index(['status', 'requested_at']);
            $table->index('gateway_refund_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
