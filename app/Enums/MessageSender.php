<?php

namespace App\Enums;

/**
 * Who wrote a consultation message (app `zMessageSender`).
 */
enum MessageSender: string
{
    case Patient = 'patient';
    case Doctor = 'doctor';
}
