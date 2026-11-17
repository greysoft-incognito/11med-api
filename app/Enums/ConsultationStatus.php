<?php

namespace App\Enums;

/**
 * Where a consultation stands (app `zConsultationStatus`). `pending` and `confirmed` each carry a
 * deadline (accept, then join); `cancelled` and `expired` are refunded in full.
 */
enum ConsultationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * The statuses that hold a doctor's slot.
     *
     * @return list<self>
     */
    public static function live(): array
    {
        return [self::Pending, self::Confirmed, self::InProgress];
    }

    /**
     * The statuses in which the patient is already with the doctor (app mock `engagedWith`).
     *
     * @return list<self>
     */
    public static function engaged(): array
    {
        return [self::Confirmed, self::InProgress];
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::Expired], true);
    }
}
