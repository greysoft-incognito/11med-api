<?php

namespace App\Enums;

/**
 * The screens a guide reply may open (app `zGuideRoute`), an allow-list.
 */
enum GuideRoute: string
{
    case Consult = 'consult';
    case Labs = 'labs';
    case LabTests = 'lab_tests';
    case Pharmacy = 'pharmacy';
    case AddMedication = 'add_medication';
    case Medications = 'medications';
    case Compliance = 'compliance';
    case EmergencyQr = 'emergency_qr';
    case Caregivers = 'caregivers';
    case Profile = 'profile';
    case Notifications = 'notifications';
}
