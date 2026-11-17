<?php

namespace App\Events\Caregiving;

use App\Models\Caregiver;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A caregiver accepted a patient's invitation. The patient is told who can now see their
 * schedule (the Notifications module listens).
 */
class CaregiverAccepted
{
    use Dispatchable;

    public function __construct(
        public readonly Caregiver $caregiver,
    ) {}
}
