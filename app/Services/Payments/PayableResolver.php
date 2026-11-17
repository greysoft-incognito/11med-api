<?php

namespace App\Services\Payments;

use App\Enums\PaymentPurpose;
use App\Exceptions\ApiException;
use App\Models\User;

/**
 * Finds a payer's targets through the purpose's `PayableTargets`, named in
 * `elevenmed.payments.targets`. A purpose whose module has not landed finds nothing.
 */
class PayableResolver
{
    /**
     * Every target one payment for this one covers, the named one first.
     *
     * @return non-empty-list<Payable>
     */
    public function resolve(PaymentPurpose $purpose, string $targetId, User $payer): array
    {
        $class = config("elevenmed.payments.targets.{$purpose->value}");

        $targets = is_string($class) && (class_exists($class) || app()->bound($class))
            ? app($class)->resolve($targetId, $payer)
            : [];

        $first = $targets[0] ?? null;

        if (! $first instanceof Payable || $first->paymentPurpose() !== $purpose || $first->paymentTargetId() !== $targetId) {
            throw ApiException::notFound($purpose->notFoundCode());
        }

        return array_values($targets);
    }

    /**
     * The one target, for announcing what happened to it, or null when it can no longer be found.
     */
    public function find(PaymentPurpose $purpose, string $targetId, ?User $payer): ?Payable
    {
        if ($payer === null) {
            return null;
        }

        try {
            return $this->resolve($purpose, $targetId, $payer)[0];
        } catch (ApiException) {
            return null;
        }
    }
}
