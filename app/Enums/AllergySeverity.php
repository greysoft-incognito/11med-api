<?php

namespace App\Enums;

/**
 * `ALLERGY_SEVERITIES` (app `src/constants/enums.ts`).
 */
enum AllergySeverity: string
{
    case Mild = 'mild';
    case Moderate = 'moderate';
    case Severe = 'severe';
}
