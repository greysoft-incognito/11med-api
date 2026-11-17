<?php

namespace App\Services\Payments;

use App\Enums\PaymentPurpose;
use App\Enums\PaymentShareState;
use App\Enums\PaymentStatus;
use App\Models\PaymentTarget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reads targets' shares: which payment a target currently answers to, and which stand paid.
 */
class PaymentShares
{
    public function current(PaymentPurpose $purpose, string $targetId): ?PaymentTarget
    {
        return $this->currentOf($purpose, [$targetId])->first();
    }

    /**
     * Each target's standing paid share when there is one, else its newest, in one query.
     *
     * @param  list<string>  $targetIds
     * @return Collection<int, PaymentTarget>
     */
    public function currentOf(PaymentPurpose $purpose, array $targetIds): Collection
    {
        $standingPaid = "CASE WHEN payments.status = 'paid' AND payment_targets.state NOT IN ('released', 'refunded') THEN 0 ELSE 1 END";

        $ids = PaymentTarget::query()
            ->join('payments', 'payments.id', '=', 'payment_targets.payment_id')
            ->where('payment_targets.purpose', $purpose->value)
            ->whereIn('payment_targets.target_id', $targetIds)
            ->whereNull('payments.deleted_at')
            ->selectRaw('DISTINCT ON (payment_targets.target_id) payment_targets.id')
            ->orderBy('payment_targets.target_id')
            ->orderByRaw($standingPaid)
            ->orderByDesc('payment_targets.id')
            ->pluck('id');

        return PaymentTarget::query()
            ->with('payment')
            ->whereIn('id', $ids)
            ->get();
    }

    /**
     * Shares of these targets that a payment has settled and that still stand.
     *
     * @param  list<string>  $targetIds
     * @return Collection<int, PaymentTarget>
     */
    public function paid(PaymentPurpose $purpose, array $targetIds, ?int $exceptPayment = null): Collection
    {
        return PaymentTarget::query()
            ->where('purpose', $purpose->value)
            ->whereIn('target_id', $targetIds)
            ->when($exceptPayment !== null, fn(Builder $q) => $q->where('payment_id', '<>', $exceptPayment))
            ->whereNotIn('state', [PaymentShareState::Released->value, PaymentShareState::Refunded->value])
            ->whereHas('payment', fn(Builder $q) => $q->where('status', PaymentStatus::Paid->value))
            ->get();
    }
}
