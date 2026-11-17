<?php

namespace App\Listeners\Laboratories;

use App\Events\Identity\AccountErasing;
use App\Services\Laboratories\BookingRecords;

/**
 * An erased patient's bookings and requests go with them, and the results documents with those.
 */
class ForgetErasedLabRecords
{
    public function __construct(
        private readonly BookingRecords $bookings,
    ) {}

    public function handle(AccountErasing $event): void
    {
        $this->bookings->forget($event->user);
    }
}
