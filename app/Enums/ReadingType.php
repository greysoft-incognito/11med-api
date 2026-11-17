<?php

namespace App\Enums;

/**
 * A reading's `type` (app `READING_TYPES`).
 */
enum ReadingType: string
{
    case BloodPressure = 'blood_pressure';
    case BloodGlucose = 'blood_glucose';
    case Weight = 'weight';
}
