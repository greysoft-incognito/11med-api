<?php

namespace App\Services\Pharmacy;

use App\Enums\OrderStatus;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\Order;
use App\Models\User;
use App\Services\Audit\AuditLog;
use App\Support\LikePattern;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Every pharmacy's orders, for the team: found when a patient or pharmacy calls, and cancelled
 * through the order book's own rule when they cannot be served.
 */
class OrderDesk
{
    public const OPEN = 'open';

    public function __construct(
        private readonly OrderBook $book,
        private readonly OrderPayments $orderPayments,
        private readonly AuditLog $audit,
    ) {}

    /**
     * Newest first; `q` over reference, patient name and phone, and pharmacy name.
     */
    public function list(?string $status, ?string $search, ListQuery $list): Page
    {
        $search = trim((string) $search);
        $pattern = LikePattern::contains($search);
        $phone = preg_match(PhoneNumber::PATTERN, $search) === 1 ? LikePattern::contains(PhoneNumber::normalise($search)) : $pattern;

        $query = Order::query()
            ->with(PatientOrders::RELATIONS)
            ->when($status === self::OPEN, fn(Builder $q) => $q->whereIn('status', OrderStatus::open()))
            ->when($status !== null && $status !== self::OPEN, fn(Builder $q) => $q->where('status', $status))
            ->when($search !== '', fn(Builder $q) => $q->where(fn(Builder $q) => $q
                ->whereLike('reference', $pattern)
                ->orWhereHas('patient', fn(Builder $q) => $q
                    ->whereLike('full_name', $pattern)
                    ->orWhereLike('phone', $phone))
                ->orWhereHas('organisation', fn(Builder $q) => $q->whereLike('name', $pattern))))
            ->orderByDesc('placed_at')
            ->orderByDesc('id');

        $page = $list->paginate($query);
        $this->orderPayments->attach($page->items);

        return $page;
    }

    public function find(string $id): Order
    {
        $order = $this->locate($id);

        return $this->orderPayments->attach($order->load(PatientOrders::RELATIONS));
    }

    /**
     * The patient reads the reason; what was paid is refunded.
     */
    public function cancel(User $actor, string $id, string $reason): Order
    {
        $order = DB::transaction(function () use ($actor, $id, $reason) {
            $order = $this->locate($id, lock: true);

            $this->book->close($order, 'team', $reason);
            $this->audit->record($actor, 'order.cancelled', $order, ['reason' => $reason]);

            return $order;
        });

        return $this->orderPayments->attach($order->refresh()->load(PatientOrders::RELATIONS));
    }

    private function locate(string $id, bool $lock = false): Order
    {
        $order = Order::query()
            ->where('uuid', $id)
            ->when($lock, fn(Builder $q) => $q->lockForUpdate())
            ->first();

        return $order ?? throw ApiException::notFound(Order::NOT_FOUND);
    }
}
