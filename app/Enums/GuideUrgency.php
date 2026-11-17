<?php

namespace App\Enums;

/**
 * How soon the person should be seen (app `zGuideUrgency`). `emergency` turns the reply into an alert.
 */
enum GuideUrgency: string
{
    case None = 'none';
    case SeeSoon = 'see_soon';
    case Emergency = 'emergency';
}
