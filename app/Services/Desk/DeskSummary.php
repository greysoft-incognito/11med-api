<?php

namespace App\Services\Desk;

use App\Services\Consultations\Payouts;
use App\Services\Doctors\DoctorApplications;
use App\Services\Partners\Organisations;
use App\Services\Subscriptions\Subscriptions;

/**
 * What waits on the team's desk (portal `zAdminSummary`), each figure from the module that owns
 * it, each a single count in SQL.
 */
class DeskSummary
{
    public function __construct(
        private readonly DoctorApplications $doctors,
        private readonly Organisations $partners,
        private readonly Payouts $payouts,
        private readonly Subscriptions $subscriptions,
    ) {}

    /**
     * @return array{doctors_awaiting_review: int, doctors_oldest_waiting_hours: int|null, partners_awaiting_review: int, payouts_pending: int, payouts_pending_kobo: int, premium_active: int}
     */
    public function read(): array
    {
        $doctors = $this->doctors->awaitingReview();
        $payouts = $this->payouts->pendingTotals();

        return [
            'doctors_awaiting_review' => $doctors['count'],
            'doctors_oldest_waiting_hours' => $doctors['oldest_hours'],
            'partners_awaiting_review' => $this->partners->awaitingReview(),
            'payouts_pending' => $payouts['count'],
            'payouts_pending_kobo' => $payouts['kobo'],
            'premium_active' => $this->subscriptions->activeCount(),
        ];
    }
}
