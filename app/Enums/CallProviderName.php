<?php

namespace App\Enums;

/**
 * Which SDK the app opens a call with (app `zCallProvider`).
 */
enum CallProviderName: string
{
    case Mock = 'mock';
    case Daily = 'daily';
    case Agora = 'agora';
}
