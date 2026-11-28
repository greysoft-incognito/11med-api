<?php

namespace App\Enums;

/**
 * A notification's `category` (app `zNotificationCategory`, portal `zPortalNotificationCategory`),
 * which also keys its preferences. Each role is offered only its own categories.
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

    case PharmacyOrder = 'pharmacy_order';
    case LabBooking = 'lab_booking';
    case DoctorReview = 'doctor_review';
    case PartnerApplication = 'partner_application';
    case PaymentAlert = 'payment_alert';
    case Announcement = 'announcement';

    /**
     * The categories a role is told about, in the order its preferences list them.
     *
     * @return list<self>
     */
    public static function for(?Role $role): array
    {
        return match ($role) {
            Role::Doctor => [self::Consultation, self::Account, self::Announcement],
            Role::Pharmacy => [self::PharmacyOrder, self::Account, self::Announcement],
            Role::Laboratory => [self::LabBooking, self::Account, self::Announcement],
            Role::Admin => [self::DoctorReview, self::PartnerApplication, self::PaymentAlert, self::Account, self::Announcement],
            default => [
                self::MedicationReminder,
                self::MissedDose,
                self::RefillReminder,
                self::HmoExpiry,
                self::Caregiver,
                self::Account,
                self::Consultation,
                self::Prescription,
                self::Order,
                self::LabRequest,
                self::LabResult,
                self::Announcement,
            ],
        };
    }

    /**
     * @return list<string>
     */
    public static function valuesFor(?Role $role): array
    {
        return array_map(fn(self $category) => $category->value, self::for($role));
    }
}
