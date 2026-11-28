<?php

namespace App\Enums;

/**
 * The record a support ticket may be about.
 */
enum SupportLinkKind: string
{
    case Order = 'order';
    case LabBooking = 'lab_booking';
    case Consultation = 'consultation';
}
