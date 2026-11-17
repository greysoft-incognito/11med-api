<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders (`zOrder`, `zPharmacyOrder`): one per pharmacy of a checkout, the checkout's orders sharing
 * a `checkout_id` and one payment. `payment_status` mirrors the payment for the pharmacy's list,
 * written only on `PaymentSucceeded` and `PaymentRefunded`. Lines copy the medicine as sold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->string('reference', 24)->unique();
            $table->uuid('checkout_id')->index();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->string('status', 16);
            $table->string('fulfilment', 16);
            $table->unsignedBigInteger('subtotal_kobo');
            $table->unsignedBigInteger('delivery_fee_kobo');
            $table->unsignedBigInteger('total_kobo');
            $table->jsonb('address')->nullable();
            $table->string('payment_status', 16);
            $table->string('payment_channel', 16)->nullable();
            $table->timestampTz('placed_at');
            $table->timestampTz('estimated_delivery_at')->nullable();
            $table->timestampTz('ready_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancelled_by', 16)->nullable();
            $table->string('cancel_reason', 200)->nullable();
            $table->jsonb('rider')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['patient_id', 'placed_at']);
            $table->index(['organisation_id', 'status', 'placed_at']);
            $table->index(['organisation_id', 'completed_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('drug_id')->constrained('drugs');
            $table->string('drug_name', 120);
            $table->string('dosage_strength', 60);
            $table->unsignedBigInteger('price_kobo');
            $table->unsignedInteger('quantity');
            $table->boolean('requires_prescription');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['drug_id', 'order_id']);
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('drug_id')->constrained('drugs')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestampsTz();
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->unique(['patient_id', 'drug_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
