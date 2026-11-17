<?php

namespace App\Listeners\Consultations;

use App\Events\Payments\PaymentSucceeded;
use App\Models\Consultation;
use App\Services\Consultations\PaidConsultations;

/**
 * A consultation's payment cleared: the doctor now sees the request.
 */
class SettleConsultationPayment
{
    public function __construct(
        private readonly PaidConsultations $paid,
    ) {}

    public function handle(PaymentSucceeded $event): void
    {
        if ($event->target instanceof Consultation) {
            $this->paid->settle($event->target);
        }
    }
}
