<?php

namespace App\Enums;

/**
 * Where a doctor sits in the review (app `zVerificationStatus`, concept §4.3).
 */
enum VerificationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
}
