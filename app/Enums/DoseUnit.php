<?php

namespace App\Enums;

/**
 * How a liquid dose is measured (app `LIQUID_UNITS`, handover §5.2); null for a form that counts itself.
 */
enum DoseUnit: string
{
    case Ml = 'ml';
    case Spoon5ml = 'spoon_5ml';
}
