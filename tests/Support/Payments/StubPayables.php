<?php

namespace Tests\Support\Payments;

use App\Models\User;
use App\Services\Payments\Payable;
use App\Services\Payments\PayableSource;

/**
 * The source the tests bind in place of a module's: finds stub targets by id, the patient's own only.
 */
class StubPayables implements PayableSource
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

    public function find(User $patient, string $targetId): ?Payable
    {
        $target = $this->targets[$targetId] ?? null;

        return $target?->patient->is($patient) ? $target : null;
    }
}
