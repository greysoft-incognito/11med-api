<?php

namespace App\Http\Resources\Pharmacy;

use App\Http\Resources\ApiResource;
use App\Models\CartItem;
use App\Services\Pharmacy\CartView;
use Illuminate\Http\Request;

/**
 * `zCart`.
 *
 * @property CartView $resource
 */
class CartResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $cart = $this->resource;

        return [
            'items' => $cart->lines
                ->map(fn(CartItem $line) => [
                    'drug_id' => $line->drug->uuid,
                    'drug_name' => $line->drug->name,
                    'dosage_strength' => $line->drug->dosage_strength,
                    'price_kobo' => $line->drug->price_kobo,
                    'quantity' => $line->quantity,
                    'requires_prescription' => $line->drug->requires_prescription,
                    'pharmacy_id' => $line->drug->organisation->uuid,
                    'pharmacy_name' => $line->drug->organisation->name,
                ])
                ->all(),
            'subtotal_kobo' => $cart->subtotalKobo,
            'delivery_fee_kobo' => $cart->deliveryFeeKobo,
            'total_kobo' => $cart->totalKobo(),
            'blocked_items' => $cart->blocked,
        ];
    }
}
