<?php

namespace App\Http\Resources\Pharmacy;

use App\Http\Resources\ApiResource;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

/**
 * `zOrder`, as the patient reads it. The payment summary is attached by `OrderPayments`.
 *
 * @property Order $resource
 */
class OrderResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $order = $this->resource;
        $pharmacy = $order->organisation;

        return [
            'id' => $order->uuid,
            'reference' => $order->reference,
            'status' => $order->status->value,
            'fulfilment' => $order->fulfilment->value,
            'pharmacy_id' => $pharmacy->uuid,
            'pharmacy_name' => $pharmacy->name,
            'pharmacy_address' => collect([$pharmacy->address, $pharmacy->city, $pharmacy->state])
                ->filter()
                ->join(', ') ?: null,
            'pharmacy_phone' => $pharmacy->contact_phone,
            ...$this->body($order),
        ];
    }

    /**
     * What the patient's and the pharmacy's view of an order share.
     *
     * @return array<string, mixed>
     */
    protected function body(Order $order): array
    {
        $pharmacy = $order->organisation;

        return [
            'items' => $order->items
                ->map(fn(OrderItem $item) => [
                    'drug_id' => $item->drug?->uuid,
                    'drug_name' => $item->drug_name,
                    'dosage_strength' => $item->dosage_strength,
                    'price_kobo' => $item->price_kobo,
                    'quantity' => $item->quantity,
                    'requires_prescription' => $item->requires_prescription,
                    'pharmacy_id' => $pharmacy->uuid,
                    'pharmacy_name' => $pharmacy->name,
                ])
                ->all(),
            'subtotal_kobo' => $order->subtotal_kobo,
            'delivery_fee_kobo' => $order->delivery_fee_kobo,
            'total_kobo' => $order->total_kobo,
            'address' => $order->address,
            'payment' => $order->getRelation('payment'),
            'placed_at' => $this->iso($order->placed_at),
            'estimated_delivery_at' => $this->iso($order->estimated_delivery_at),
            'ready_at' => $this->iso($order->ready_at),
            'completed_at' => $this->iso($order->completed_at),
            'cancelled_at' => $this->iso($order->cancelled_at),
            'cancellation' => $order->cancelled_by === null ? null : ['by' => $order->cancelled_by, 'reason' => (string) $order->cancel_reason],
            'rider' => $order->rider,
        ];
    }
}
