<?php

namespace App\Events\Doctors;

use App\Models\DoctorProfile;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A doctor's application joined the team's review queue.
 */
class DoctorSubmitted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly DoctorProfile $profile,
    ) {}
}
