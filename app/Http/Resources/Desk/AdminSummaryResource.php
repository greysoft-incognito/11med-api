<?php

namespace App\Http\Resources\Desk;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * Portal `zAdminSummary`.
 *
 * @property array{doctors_awaiting_review: int, doctors_oldest_waiting_hours: int|null, partners_awaiting_review: int, payouts_pending: int, payouts_pending_kobo: int, premium_active: int} $resource
 */
class AdminSummaryResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $summary = $this->resource;

        return [
            'doctors_awaiting_review' => $summary['doctors_awaiting_review'],
            'doctors_oldest_waiting_hours' => $summary['doctors_oldest_waiting_hours'],
            'partners_awaiting_review' => $summary['partners_awaiting_review'],
            'payouts_pending' => $summary['payouts_pending'],
            'payouts_pending_kobo' => $summary['payouts_pending_kobo'],
            'premium_active' => $summary['premium_active'],
        ];
    }
}
