<?php

namespace App\Enums;

/**
 * What a support ticket is about (clients' `zSupportCategory`).
 */
enum SupportCategory: string
{
    case Account = 'account';
    case Payment = 'payment';
    case Order = 'order';
    case LabBooking = 'lab_booking';
    case Consultation = 'consultation';
    case Technical = 'technical';
    case Other = 'other';
}
