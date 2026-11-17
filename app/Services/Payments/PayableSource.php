<?php

namespace App\Services\Payments;

use App\Models\User;

/**
 * Finds a patient's own payable target by its public id. Each purpose's module provides one at the
 * class name `PaymentPurpose::sourceClass()` names.
 */
interface PayableSource
{
    public function find(User $patient, string $targetId): ?Payable;
}
