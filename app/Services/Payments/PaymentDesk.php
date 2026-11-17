<?php

namespace App\Services\Payments;

use App\Enums\PaymentPurpose;
use App\Enums\PaymentShareState;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\PaymentTarget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The admin desk (portal handover §6, "Payments, the desk"): every order and booking share the
 * gateway has seen, newest first, filtered and searched in SQL. Read-only.
 */
class PaymentDesk
{
    public function list(ListQuery $list, ?string $status, ?string $search): Page
    {
        $shownAs = PaymentShareState::shownAsSql();
        $name = "COALESCE(users.full_name, CONCAT_WS(' ', users.firstname, users.lastname))";

        $query = PaymentTarget::query()
            ->join('payments', 'payments.id', '=', 'payment_targets.payment_id')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->whereNull('payments.deleted_at')
            ->whereIn('payment_targets.purpose', PaymentPurpose::onTheDesk())
            ->select('payment_targets.*')
            ->selectRaw("{$name} AS patient_name")
            ->selectRaw('users.phone AS patient_phone')
            ->with('payment')
            ->when($status !== null, fn(Builder $q) => $q->whereRaw("{$shownAs} = ?", [$status]))
            ->when($search !== null && $search !== '', function (Builder $q) use ($search, $name) {
                $like = '%' . addcslashes($search, '%_\\') . '%';

                $q->where(fn(Builder $or) => $or
                    ->where('payments.reference', 'ILIKE', $like)
                    ->orWhere('payment_targets.target_reference', 'ILIKE', $like)
                    ->orWhere('payment_targets.partner_name', 'ILIKE', $like)
                    ->orWhere('users.phone', 'ILIKE', $like)
                    ->orWhereRaw("{$name} ILIKE ?", [$like]));
            })
            ->orderByDesc('payment_targets.id');

        return $list->paginate($query);
    }
}
