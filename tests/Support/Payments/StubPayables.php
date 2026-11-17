<?php

namespace Tests\Support\Payments;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Payments\PayableTargets;

/**
 * The targets the tests bind in place of a module's: a stub by id with its siblings, the payer's own only.
 */
class StubPayables implements PayableTargets
{
    /**
     * @var array<string, StubPayable>
     */
    public array $targets = [];

    public function add(StubPayable ...$targets): void
    {
        foreach ($targets as $target) {
            $this->targets[$target->id] = $target;
        }
    }

    public function resolve(string $targetId, User $payer): array
    {
        $target = $this->targets[$targetId] ?? null;

        if ($target === null || ! $target->patient->is($payer)) {
            throw ApiException::notFound($target?->purpose->notFoundCode() ?? 'NOT_FOUND');
        }

        return [$target, ...$target->siblings];
    }
}
