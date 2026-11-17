<?php

namespace App\Services\Payments;

use App\Enums\PaymentPurpose;
use App\Models\User;

/**
 * Something a patient pays for: an order, a lab booking or a consultation. The one interface the
 * Payments module (M9) knows; the module that owns the record implements it on its model.
 */
interface Payable
{
    public function paymentPurpose(): PaymentPurpose;

    /**
     * The public id the client names as `target_id`.
     */
    public function paymentTargetId(): string;

    /**
     * The reference the admin desk shows for the target (`ORD-…`, `LAB-…`; a consultation may give
     * its id).
     */
    public function paymentReference(): string;

    /**
     * The pharmacy or laboratory the payment reaches, or null for a consultation.
     */
    public function paymentPartnerName(): ?string;

    public function payer(): User;

    public function amountKobo(): int;

    /**
     * Refuses a payment the record's own state forbids with the contract's 422 code
     * (`ORDER_CANCELLED`, `BOOKING_CANCELLED`, `CONSULTATION_CLOSED`). `ALREADY_PAID` is checked by
     * the Payments module.
     */
    public function assertPayable(): void;
}
