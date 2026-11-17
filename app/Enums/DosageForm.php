<?php

namespace App\Enums;

/**
 * A medication's `dosage_form` (app `DOSAGE_FORMS`).
 */
enum DosageForm: string
{
    case Tablet = 'tablet';
    case Capsule = 'capsule';
    case Syrup = 'syrup';
    case Injection = 'injection';
    case Cream = 'cream';
    case Inhaler = 'inhaler';
    case Drops = 'drops';
}
