<?php

namespace App\Enums;

/**
 * What a payment is for (app `zPaymentPurpose`).
 */
enum PaymentPurpose: string
{
    case Order = 'order';
    case LabBooking = 'lab_booking';
    case Consultation = 'consultation';
}
