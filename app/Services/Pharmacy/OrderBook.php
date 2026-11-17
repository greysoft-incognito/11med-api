<?php

namespace App\Services\Pharmacy;

use App\Enums\Fulfilment;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Services\Payments\Payments;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * A pharmacy's orders (portal handover §4): only those paid, or to be paid at the counter, moved
 * one stage at a time through the stages the patient sees, or cancelled with a reason before the
 * rider or the counter has it. The only code that moves an order on.
 */
class OrderBook
{
    /** Minutes to the door when a pharmacy has not said and the hand-over names no estimate. */
    private const DEFAULT_DELIVERY_MINUTES = 45;

    public function __construct(
        private readonly OrderPayments $orderPayments,
        private readonly Payments $payments,
        private readonly OrderNotices $notices,
    ) {}

    /**
     * @param  array{status?: string|null, q?: string|null}  $filters
     */
    public function list(Pharmacy $pharmacy, array $filters, ListQuery $list): Page
    {
        $status = $filters['status'] ?? null;
        $q = trim((string) ($filters['q'] ?? ''));
        $phone = preg_match(PhoneNumber::PATTERN, $q) === 1 ? PhoneNumber::normalise($q) : $q;

        $query = $this
            ->own($pharmacy)
            ->with(PatientOrders::RELATIONS)
            ->when($status === 'open', fn(Builder $query) => $query->whereIn('status', OrderStatus::open()))
            ->when($status !== null && $status !== 'open', fn(Builder $query) => $query->where('status', $status))
            ->when($q !== '', fn(Builder $query) => $query->where(fn(Builder $query) => $query
                ->whereLike('reference', "%{$q}%")
                ->orWhereHas('patient', fn(Builder $query) => $query
                    ->whereLike('full_name', "%{$q}%")
                    ->orWhereLike('phone', "%{$phone}%"))))
            ->orderByDesc('placed_at')
            ->orderByDesc('id');

        $page = $list->paginate($query);
        $this->orderPayments->attach($page->items);

        return $page;
    }

    public function find(Pharmacy $pharmacy, string $id): Order
    {
        $order = $this->locate($pharmacy, $id);

        return $this->orderPayments->attach($order->load(PatientOrders::RELATIONS));
    }

    /**
     * One step on. Handing a delivery to the rider names the rider, since the patient sees them next.
     *
     * @param  array{rider?: array{name: string, phone: string, vehicle?: string|null}|null, estimated_delivery_at?: string|null}  $input
     */
    public function advance(Pharmacy $pharmacy, string $id, array $input): Order
    {
        $order = DB::transaction(function () use ($pharmacy, $id, $input) {
            $order = $this->locate($pharmacy, $id, lock: true);
            $order->load(PatientOrders::RELATIONS);

            if ($order->status === OrderStatus::Cancelled) {
                throw ApiException::unprocessable('ORDER_CANCELLED');
            }

            $next = $order->status->next();

            if ($next === null) {
                throw ApiException::unprocessable('ORDER_FINAL');
            }

            $now = now();
            $handingOver = $next === OrderStatus::Ready && $order->fulfilment === Fulfilment::Delivery;

            if ($handingOver && empty($input['rider'])) {
                throw ApiException::unprocessable('RIDER_REQUIRED', 'rider');
            }

            $order->status = $next;

            if ($handingOver) {
                $order->rider = [
                    'name' => $input['rider']['name'],
                    'phone' => PhoneNumber::normalise($input['rider']['phone']),
                    'vehicle' => $input['rider']['vehicle'] ?? null,
                ];
                $order->estimated_delivery_at = isset($input['estimated_delivery_at'])
                    ? CarbonImmutable::parse($input['estimated_delivery_at'])
                    : $now->copy()->addMinutes($pharmacy->delivery_minutes ?? self::DEFAULT_DELIVERY_MINUTES);
            }

            $order->ready_at = $next === OrderStatus::Ready ? $now : $order->ready_at;
            $order->completed_at = $next === OrderStatus::Completed ? $now : $order->completed_at;
            $order->save();

            if ($next === OrderStatus::Completed && $order->isAtCounter() && $order->payment_status === PaymentStatus::Pending) {
                $this->payments->collectAtCounter($order);
            }

            $this->notices->moved($order);

            return $order;
        });

        return $this->orderPayments->attach($order->refresh()->load(PatientOrders::RELATIONS));
    }

    /**
     * The pharmacy's cancellation, with the reason the patient reads, before the rider or the counter has it.
     */
    public function cancel(Pharmacy $pharmacy, string $id, string $reason): Order
    {
        $order = DB::transaction(function () use ($pharmacy, $id, $reason) {
            $order = $this->locate($pharmacy, $id, lock: true);
            $order->load(PatientOrders::RELATIONS);

            if ($order->status === OrderStatus::Cancelled) {
                throw ApiException::unprocessable('ORDER_CANCELLED');
            }

            if (in_array($order->status, [OrderStatus::Ready, OrderStatus::Completed], true)) {
                throw ApiException::unprocessable('ORDER_STARTED');
            }

            $order->fill([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => 'pharmacy',
                'cancel_reason' => $reason,
            ]);
            $order->save();

            $this->payments->refund($order);
            $this->notices->cancelled($order);

            return $order;
        });

        return $this->orderPayments->attach($order->refresh()->load(PatientOrders::RELATIONS));
    }

    private function own(Pharmacy $pharmacy): Builder
    {
        return Order::query()
            ->visibleToPharmacy()
            ->where('organisation_id', $pharmacy->organisation_id);
    }

    private function locate(Pharmacy $pharmacy, string $id, bool $lock = false): Order
    {
        $order = $this
            ->own($pharmacy)
            ->where('uuid', $id)
            ->when($lock, fn(Builder $query) => $query->lockForUpdate())
            ->first();

        if ($order === null) {
            throw ApiException::notFound(Order::NOT_FOUND);
        }

        return $order;
    }
}
