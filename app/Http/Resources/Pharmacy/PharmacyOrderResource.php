<?php

namespace App\Http\Resources\Pharmacy;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Pharmacy\PrescribedDrugs;
use Illuminate\Http\Request;

/**
 * `zPharmacyOrder`: the order plus who placed it and how to reach them.
 *
 * @property Order $resource
 */
class PharmacyOrderResource extends OrderResource
{
    public function toArray(Request $request): array
    {
        $order = $this->resource;

        return [
            'id' => $order->uuid,
            'reference' => $order->reference,
            'status' => $order->status->value,
            'fulfilment' => $order->fulfilment->value,
            'pharmacy_id' => $order->organisation->uuid,
            ...$this->body($order),
            'patient_name' => $order->patient->full_name,
            'patient_phone' => $order->patient->phone,
            'prescription_verified' => $this->prescriptionVerified($order),
        ];
    }

    /**
     * Every prescription-only line covered by a valid prescription; true when there is none.
     */
    private function prescriptionVerified(Order $order): bool
    {
        $needed = $order->items->filter(fn(OrderItem $item) => $item->requires_prescription);

        if ($needed->isEmpty() || $order->status === OrderStatus::Cancelled) {
            return $needed->isEmpty();
        }

        $allowed = array_flip(app(PrescribedDrugs::class)->names($order->patient));

        return $needed->every(fn(OrderItem $item) => isset($allowed[mb_strtolower(trim($item->drug_name))]));
    }
}
