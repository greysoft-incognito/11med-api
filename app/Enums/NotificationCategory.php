<?php

namespace App\Enums;

/**
 * A notification's `category` (app `zNotificationCategory`), which also keys its preferences.
 */
enum NotificationCategory: string
{
    case MedicationReminder = 'medication_reminder';
    case MissedDose = 'missed_dose';
    case RefillReminder = 'refill_reminder';
    case HmoExpiry = 'hmo_expiry';
    case Caregiver = 'caregiver';
    case Account = 'account';
    case Consultation = 'consultation';
    case Prescription = 'prescription';
    case Order = 'order';
    case LabRequest = 'lab_request';
    case LabResult = 'lab_result';
}
