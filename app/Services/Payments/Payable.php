<?php

namespace App\Services\Payments;

use App\Enums\PaymentPurpose;
use App\Models\User;

/**
 * Something a patient pays for: an order, a lab booking or a consultation. The owning module's model
 * implements it; payments know nothing else about it.
 */
interface Payable
{
    public function paymentPurpose(): PaymentPurpose;

    /**
     * The target's public id.
     */
    public function paymentTargetId(): string;

    public function paymentPatient(): User;

    public function paymentAmountKobo(): int;

    /**
     * The reference staff read on the admin desk (`ORD-…`), or the public id where there is none.
     */
    public function paymentTargetReference(): string;

    public function paymentPartnerName(): ?string;

    /**
     * Throws the module's 422 when the target can no longer be paid for (`ORDER_CANCELLED`, …).
     */
    public function ensurePayable(): void;

    /**
     * What one payment for this target covers, the target included: every order of a checkout, or
     * just the target itself.
     *
     * @return list<Payable>
     */
    public function paymentTargets(): array;
}
