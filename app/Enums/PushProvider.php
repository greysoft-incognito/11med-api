<?php

namespace App\Enums;

enum PushProvider: string
{
    case Apns = 'apns';
    case Fcm = 'fcm';
}
