<?php

namespace App\Services\Pharmacy;

use App\Enums\Fulfilment;
use App\Enums\OrderStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\User;
use App\Services\Payments\Payments;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Checkout (app spec §8.3, handover §5.3 and §16): the cart becomes one order per pharmacy, all
 * under one payment, started before the answer so the app can open it. A pickup may instead be paid
 * at the counter when collected.
 */
class Checkout
{
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly Cart $cart,
        private readonly Payments $payments,
        private readonly OrderPayments $orderPayments,
    ) {}

    /**
     * @param  array{fulfilment: string, address?: array<string, mixed>|null, channel?: string|null}  $input
     * @return Collection<int, Order>
     */
    public function place(User $patient, array $input): Collection
    {
        $fulfilment = Fulfilment::from($input['fulfilment']);
        $channel = isset($input['channel']) ? PaymentChannel::from($input['channel']) : null;

        if ($channel === PaymentChannel::Counter && $fulfilment !== Fulfilment::Pickup) {
            throw ApiException::unprocessable('COUNTER_IS_FOR_PICKUP', 'channel');
        }

        $orders = DB::transaction(function () use ($patient, $input, $fulfilment, $channel) {
            $patient->newQuery()->lockForUpdate()->find($patient->id);

            $lines = $this->cart->lines($patient);
            $this->ensureOrderable($patient, $lines);

            $checkout = (string) Str::uuid7();
            $now = now();

            $orders = $lines
                ->groupBy('drug.organisation_id')
                ->map(fn(Collection $group) => $this->order($patient, $group, $checkout, $fulfilment, $channel, $input['address'] ?? null, $now))
                ->values();

            $this->cart->clear($patient);

            if ($channel === PaymentChannel::Counter) {
                $orders->each(fn(Order $order) => $this->payments->openAtCounter($order));
            } else {
                $this->payments->initialise($orders->all(), $patient, $channel);
            }

            return $orders;
        });

        return $this->orderPayments->attach($orders);
    }

    /**
     * @param  Collection<int, CartItem>  $lines
     */
    private function ensureOrderable(User $patient, Collection $lines): void
    {
        if ($lines->isEmpty()) {
            throw ApiException::unprocessable('CART_EMPTY');
        }

        $blocked = $this->cart->price($patient, $lines)->blocked;

        if ($blocked !== []) {
            throw ApiException::unprocessable('PRESCRIPTION_REQUIRED', null, ['blocked_items' => $blocked]);
        }

        $out = $this->cart->outOfStock($lines);

        if ($out !== []) {
            throw ApiException::unprocessable('OUT_OF_STOCK', null, ['drug_ids' => $out]);
        }
    }

    /**
     * @param  Collection<int, CartItem>  $lines  One pharmacy's lines.
     * @param  array<string, mixed>|null  $address
     */
    private function order(User $patient, Collection $lines, string $checkout, Fulfilment $fulfilment, ?PaymentChannel $channel, ?array $address, mixed $now): Order
    {
        $organisationId = $lines->first()->drug->organisation_id;
        $subtotal = $this->cart->subtotal($lines);
        $delivery = $fulfilment === Fulfilment::Delivery
            ? Pharmacy::query()->where('organisation_id', $organisationId)->firstOrFail()->deliveryFeeFor($subtotal)
            : 0;

        $order = Order::create([
            'reference' => $this->freshReference(),
            'checkout_id' => $checkout,
            'patient_id' => $patient->id,
            'organisation_id' => $organisationId,
            'status' => OrderStatus::Placed,
            'fulfilment' => $fulfilment,
            'subtotal_kobo' => $subtotal,
            'delivery_fee_kobo' => $delivery,
            'total_kobo' => $subtotal + $delivery,
            'address' => $fulfilment === Fulfilment::Delivery ? $this->address($address) : null,
            'payment_status' => PaymentStatus::Pending,
            'payment_channel' => $channel === PaymentChannel::Counter ? $channel : null,
            'placed_at' => $now,
        ]);

        $order
            ->items()
            ->createMany($lines
                ->map(fn(CartItem $line) => [
                    'drug_id' => $line->drug->id,
                    'drug_name' => $line->drug->name,
                    'dosage_strength' => $line->drug->dosage_strength,
                    'price_kobo' => $line->drug->price_kobo,
                    'quantity' => $line->quantity,
                    'requires_prescription' => $line->drug->requires_prescription,
                ])
                ->all());

        $order->setRelation('patient', $patient);
        $order->setRelation('organisation', $lines->first()->drug->organisation);

        return $order->load('items');
    }

    /**
     * @param  array<string, mixed>|null  $address
     * @return array<string, string|null>
     */
    private function address(?array $address): array
    {
        return [
            'line1' => $address['line1'],
            'line2' => $address['line2'] ?? null,
            'city' => $address['city'],
            'state' => $address['state'],
            'directions' => $address['directions'] ?? null,
        ];
    }

    private function freshReference(): string
    {
        do {
            $reference = 'ORD-' . collect(range(1, 8))
                ->map(fn() => self::REFERENCE_ALPHABET[random_int(0, strlen(self::REFERENCE_ALPHABET) - 1)])
                ->join('');
        } while (Order::withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }
}
