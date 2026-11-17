<?php

namespace App\Enums;

/**
 * A medicine's form (app and portal `DOSAGE_FORMS`).
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
