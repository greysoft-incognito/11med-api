<?php

namespace App\Services\Consultations;

use App\Enums\ConsultationStatus;
use App\Enums\SessionType;
use App\Models\Consultation;

/**
 * The timers of app handover §2.3, run by the scheduler every minute: bookings past their acceptance
 * or join deadline expire and are refunded; chats past their window close. Never on read.
 */
class Deadlines
{
    public function __construct(
        private readonly ConsultationLifecycle $lifecycle,
        private readonly ConsultationClock $clock,
    ) {}

    /**
     * @return array{expired: int, closed: int}
     */
    public function settle(): array
    {
        $expired = 0;
        $closed = 0;

        Consultation::query()
            ->whereIn('status', [ConsultationStatus::Pending, ConsultationStatus::Confirmed])
            ->where('expires_at', '<=', now())
            ->select(['id'])
            ->chunkById(200, function ($chunk) use (&$expired) {
                foreach ($chunk as $consultation) {
                    $expired += $this->lifecycle->expire($consultation) === null ? 0 : 1;
                }
            });

        Consultation::query()
            ->where('status', ConsultationStatus::InProgress)
            ->where('session_type', SessionType::Chat)
            ->whereRaw('started_at + make_interval(mins => length_minutes) <= ?', [now()])
            ->select(['id'])
            ->chunkById(200, function ($chunk) use (&$closed) {
                foreach ($chunk as $consultation) {
                    $closed += $this->lifecycle->closeChat($consultation) === null ? 0 : 1;
                }
            });

        return ['expired' => $expired, 'closed' => $closed];
    }
}
