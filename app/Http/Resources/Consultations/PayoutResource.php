<?php

namespace App\Http\Resources\Consultations;

use App\Http\Resources\ApiResource;
use App\Models\Payout;
use Illuminate\Http\Request;

/**
 * App `zPayout`: gross, commission and net, all three.
 *
 * @property Payout $resource
 */
class PayoutResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $payout = $this->resource;

        return [
            'id' => $payout->uuid,
            'gross_kobo' => $payout->gross_kobo,
            'commission_kobo' => $payout->commission_kobo,
            'net_kobo' => $payout->net_kobo,
            'consultation_count' => $payout->consultation_count,
            'status' => $payout->status->value,
            'period_start' => $this->iso($payout->period_start),
            'period_end' => $this->iso($payout->period_end),
            'paid_at' => $this->iso($payout->paid_at),
        ];
    }
}
