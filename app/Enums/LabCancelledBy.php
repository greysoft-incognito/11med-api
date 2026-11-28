<?php

namespace App\Enums;

/**
 * Who cancelled a laboratory booking (portal `zLaboratoryBooking.cancellation.by`).
 */
enum LabCancelledBy: string
{
    case Patient = 'patient';
    case Laboratory = 'laboratory';
    case Team = 'team';
}
