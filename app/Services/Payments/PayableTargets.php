<?php

namespace App\Services\Payments;

use App\Enums\PaymentPurpose;
use App\Exceptions\ApiException;
use App\Models\User;

/**
 * Resolves `{ purpose, target_id }` to the patient's own target, through the purpose's source.
 * A purpose whose module has not landed finds nothing.
 */
class PayableTargets
{
    public function find(PaymentPurpose $purpose, User $patient, string $targetId): Payable
    {
        $class = $purpose->sourceClass();

        $target = class_exists($class) || app()->bound($class)
            ? app($class)->find($patient, $targetId)
            : null;

        if (! $target instanceof Payable || $target->paymentPurpose() !== $purpose) {
            throw ApiException::notFound($purpose->notFoundCode());
        }

        return $target;
    }
}
