<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One target's share of a payment: an order of a checkout, a lab booking or a consultation, named by
 * its public id. The reference and partner are copied at payment time for the admin desk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_targets', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('purpose', 16);
            $table->uuid('target_id');
            $table->string('target_reference', 64);
            $table->string('partner_name', 160)->nullable();
            $table->bigInteger('amount_kobo');
            $table->bigInteger('refunded_kobo')->default(0);
            $table->string('state', 16);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['purpose', 'target_id']);
            $table->index(['payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_targets');
    }
};
