<?php

namespace App\Enums;

/**
 * What a payment pays for (app `zPaymentPurpose`). Each purpose's module resolves its targets through
 * the `PayableTargets` class `elevenmed.payments.targets` names.
 */
enum PaymentPurpose: string
{
    case Order = 'order';
    case LabBooking = 'lab_booking';
    case Consultation = 'consultation';

    public function notFoundCode(): string
    {
        return match ($this) {
            self::Order => 'ORDER_NOT_FOUND',
            self::LabBooking => 'LAB_BOOKING_NOT_FOUND',
            self::Consultation => 'CONSULTATION_NOT_FOUND',
        };
    }

    /**
     * Purposes the admin desk lists (portal `zAdminPayment`); consultations reach the doctor's earnings instead.
     *
     * @return list<string>
     */
    public static function onTheDesk(): array
    {
        return [self::Order->value, self::LabBooking->value];
    }
}
