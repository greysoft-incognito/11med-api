<?php

namespace App\Enums;

use App\Models\User;

/**
 * A notification's `category` (app `zNotificationCategory`, portal `zPortalNotificationCategory`),
 * which also keys its preferences. Each side of an account is offered only its own categories.
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
    case Support = 'support';

    /**
     * The categories the account is told about on one client, in the order its preferences list
     * them: the app's for its patient and doctor sides, a portal's for its partner and staff sides.
     *
     * @return list<self>
     */
    public static function for(User $user, AuthClient $client): array
    {
        $lists = $client === AuthClient::App
            ? array_map(self::forKind(...), $user->kindsOf(AccountKind::app()))
            : [
                ...array_map(self::forKind(...), $user->kindsOf(AccountKind::portal())),
                ...($user->isStaff() ? [self::forStaff()] : []),
            ];

        return array_values(array_unique(array_merge(...$lists), SORT_REGULAR));
    }

    /**
     * @return list<string>
     */
    public static function valuesFor(User $user, AuthClient $client): array
    {
        return array_map(fn(self $category) => $category->value, self::for($user, $client));
    }

    /**
     * @return list<self>
     */
    public static function forKind(AccountKind $kind): array
    {
        return match ($kind) {
            AccountKind::Doctor => [self::Consultation, self::Account, self::Announcement],
            AccountKind::Pharmacy => [self::PharmacyOrder, self::Account, self::Announcement],
            AccountKind::Laboratory => [self::LabBooking, self::Account, self::Announcement],
            AccountKind::Patient => [
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
     * @return list<self>
     */
    public static function forStaff(): array
    {
        return [self::DoctorReview, self::PartnerApplication, self::PaymentAlert, self::Account, self::Announcement];
    }
}
