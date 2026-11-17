<?php

namespace App\Enums;

/**
 * A pharmacy order's stage (app `zOrderStatus`): `ready` is with the rider or on the counter,
 * `completed` delivered or collected.
 */
enum OrderStatus: string
{
    case Placed = 'placed';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Still with the pharmacy: not completed and not cancelled.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Placed, self::Preparing, self::Ready];
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Placed => self::Preparing,
            self::Preparing => self::Ready,
            self::Ready => self::Completed,
            default => null,
        };
    }
}
