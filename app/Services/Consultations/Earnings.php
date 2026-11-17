<?php

namespace App\Services\Consultations;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * A doctor's earnings (app `zEarnings`, §7.7): what is owed and not yet in a payout, what has been
 * paid, the rate, and the newest payouts with gross, commission and net.
 */
class Earnings
{
    public function __construct(
        private readonly Payouts $payouts,
    ) {}

    /**
     * @return array{pending_kobo: int, paid_total_kobo: int, commission_percent: float, payouts: Collection<int, Payout>}
     */
    public function of(User $doctor): array
    {
        $percent = (float) config('elevenmed.consultations.commission_percent');

        $gross = (int) $this->payouts
            ->owed()
            ->where('doctor_id', $doctor->id)
            ->sum('fee_kobo');

        $paid = (int) Payout::query()
            ->where('doctor_id', $doctor->id)
            ->where('status', PayoutStatus::Paid)
            ->sum('net_kobo');

        $recent = Payout::query()
            ->where('doctor_id', $doctor->id)
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->limit(config('elevenmed.consultations.earnings_payouts'))
            ->get();

        return [
            'pending_kobo' => $gross - $this->payouts->commission($gross, $percent),
            'paid_total_kobo' => $paid,
            'commission_percent' => $percent,
            'payouts' => $recent,
        ];
    }
}
