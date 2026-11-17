<?php

namespace App\Enums;

/**
 * When a blood sugar was taken (app `GLUCOSE_CONTEXTS`).
 */
enum GlucoseContext: string
{
    case Fasting = 'fasting';
    case AfterMeal = 'after_meal';
    case Random = 'random';
}
