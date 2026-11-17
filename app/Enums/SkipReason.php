<?php

namespace App\Enums;

/**
 * Why a dose was skipped (app `SKIP_REASONS`). Every skip still counts against compliance.
 */
enum SkipReason: string
{
    case RanOut = 'ran_out';
    case DoctorAdvised = 'doctor_advised';
    case SideEffects = 'side_effects';
    case FeltBetter = 'felt_better';
    case Other = 'other';
}
