<?php

namespace App\Events\Doctors;

use App\Enums\VerificationStatus;
use App\Models\DoctorProfile;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The team decided on a doctor's application: approved, rejected, suspended or reinstated.
 */
class DoctorReviewed
{
    use Dispatchable;

    public function __construct(
        public readonly DoctorProfile $profile,
        public readonly VerificationStatus $previous,
    ) {}
}
