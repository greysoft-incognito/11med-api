<?php

namespace App\Enums;

use App\Services\Payments\PayableSource;

/**
 * What a payment pays for (app `zPaymentPurpose`). Each purpose's module supplies its targets
 * through a `PayableSource` at the class name below.
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
     * @return class-string<PayableSource>
     */
    public function sourceClass(): string
    {
        return match ($this) {
            self::Order => 'App\Services\Pharmacy\OrderPayables',
            self::LabBooking => 'App\Services\Laboratories\LabBookingPayables',
            self::Consultation => 'App\Services\Consultations\ConsultationPayables',
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
