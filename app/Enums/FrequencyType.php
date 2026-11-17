<?php

namespace App\Enums;

/**
 * A schedule's `frequency_type` (app `FREQUENCY_TYPES`). Doses come from `specific_times` alone.
 */
enum FrequencyType: string
{
    case Daily = 'daily';
    case TwiceDaily = 'twice_daily';
    case ThreeTimesDaily = 'three_times_daily';
    case FourTimesDaily = 'four_times_daily';
    case EveryXHours = 'every_x_hours';
    case Weekly = 'weekly';
    case Custom = 'custom';
}
