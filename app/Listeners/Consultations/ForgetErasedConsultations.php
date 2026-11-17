<?php

namespace App\Listeners\Consultations;

use App\Events\Identity\AccountErasing;
use App\Services\Consultations\ConsultationRecords;

/**
 * An erased patient's consultations go with them, chat and prescriptions included.
 */
class ForgetErasedConsultations
{
    public function __construct(
        private readonly ConsultationRecords $records,
    ) {}

    public function handle(AccountErasing $event): void
    {
        $this->records->forget($event->user);
    }
}
