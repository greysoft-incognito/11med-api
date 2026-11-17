<?php

namespace App\Http\Resources\Consultations;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * App `zEarnings`.
 *
 * @property array{pending_kobo: int, paid_total_kobo: int, commission_percent: float, payouts: iterable} $resource
 */
class EarningsResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $earnings = $this->resource;

        return [
            'pending_kobo' => $earnings['pending_kobo'],
            'paid_total_kobo' => $earnings['paid_total_kobo'],
            'commission_percent' => $earnings['commission_percent'],
            'payouts' => PayoutResource::collection($earnings['payouts'])->toArray($request),
        ];
    }
}
