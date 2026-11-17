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

    public function payer(): User;

    public function amountKobo(): int;

    /**
     * Refuses a payment that cannot start now with the contract's 422 code (`ALREADY_PAID`,
     * `ORDER_CANCELLED`, `BOOKING_CANCELLED`, `CONSULTATION_CLOSED`).
     */
    public function assertPayable(): void;
}
