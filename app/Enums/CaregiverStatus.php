<?php

namespace App\Enums;

/**
 * A caregiver's `status` (app `zCaregiver`): invited, or accepted and able to read.
 */
enum CaregiverStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
}
