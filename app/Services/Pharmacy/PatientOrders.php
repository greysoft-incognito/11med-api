<?php

namespace App\Services\Pharmacy;

use App\Enums\OrderStatus;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\Payments;
use Illuminate\Support\Facades\DB;

/**
 * A patient's own orders (app spec §8.3, §8.4).
 */
class PatientOrders
{
    public const RELATIONS = ['items', 'organisation', 'patient'];

    public function __construct(
        private readonly OrderPayments $orderPayments,
        private readonly Payments $payments,
    ) {}

    public function list(User $patient, ListQuery $list): Page
    {
        $query = Order::query()
            ->with(self::RELATIONS)
            ->where('patient_id', $patient->id)
            ->orderByDesc('placed_at')
            ->orderByDesc('id');

        $page = $list->paginate($query);
        $this->orderPayments->attach($page->items);

        return $page;
    }

    public function find(User $patient, string $id): Order
    {
        $order = $this->own($patient, $id);

        return $this->orderPayments->attach($order->load(self::RELATIONS));
    }

    /**
     * The patient cancels only before the pharmacy starts (§8.4), with an empty reason; what was paid is refunded.
     */
    public function cancel(User $patient, string $id): Order
    {
        $order = DB::transaction(function () use ($patient, $id) {
            $order = $this->own($patient, $id, lock: true);

            if ($order->status !== OrderStatus::Placed) {
                throw ApiException::unprocessable('ORDER_CONFIRMED');
            }

            $order->fill([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => 'patient',
                'cancel_reason' => '',
            ]);
            $order->save();

            $this->payments->refund($order->load(self::RELATIONS));

            return $order;
        });

        return $this->orderPayments->attach($order);
    }

    private function own(User $patient, string $id, bool $lock = false): Order
    {
        $order = Order::query()
            ->where('patient_id', $patient->id)
            ->where('uuid', $id)
            ->when($lock, fn($query) => $query->lockForUpdate())
            ->first();

        if ($order === null) {
            throw ApiException::notFound(Order::NOT_FOUND);
        }

        return $order;
    }
}
