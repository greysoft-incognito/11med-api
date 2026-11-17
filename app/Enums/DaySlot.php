<?php

namespace App\Enums;

/**
 * The part of the day an insight groups doses by (app `DAY_SLOTS`, handover §14).
 */
enum DaySlot: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case Evening = 'evening';
    case Night = 'night';

    public static function ofHour(int $hour): self
    {
        return match (true) {
            $hour >= 5 && $hour <= 11 => self::Morning,
            $hour >= 12 && $hour <= 16 => self::Afternoon,
            $hour >= 17 && $hour <= 20 => self::Evening,
            default => self::Night,
        };
    }
}
