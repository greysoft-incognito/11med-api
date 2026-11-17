<?php

namespace App\Enums;

/**
 * A dose's answer (app `DOSE_STATES`, spec §6.3.2).
 */
enum DoseState: string
{
    case Taken = 'taken';
    case TakenLate = 'taken_late';
    case Missed = 'missed';
    case Snoozed = 'snoozed';
    case Skipped = 'skipped';

    public function isTaken(): bool
    {
        return $this === self::Taken || $this === self::TakenLate;
    }
}
