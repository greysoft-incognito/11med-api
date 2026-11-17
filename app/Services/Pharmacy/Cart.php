<?php

namespace App\Services\Pharmacy;

use App\Exceptions\ApiException;
use App\Models\CartItem;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A patient's cart (app spec §8.2, handover §5.3): lines of listed medicines, priced with each
 * pharmacy's own delivery terms, and the prescription-only lines checkout will refuse.
 */
class Cart
{
    /** Lines a cart holds at most, so a cart is always read whole. */
    public const MAX_LINES = 50;

    /** Of one medicine in one line. */
    public const MAX_QUANTITY = 99;

    public function __construct(
        private readonly Storefront $storefront,
        private readonly PrescribedDrugs $prescribed,
    ) {}

    public function show(User $patient): CartView
    {
        $lines = $this->lines($patient);

        return $this->price($patient, $lines);
    }

    /**
     * Add, change or, with zero, remove a line.
     */
    public function set(User $patient, string $drugId, int $quantity): CartView
    {
        $drug = $this->storefront->drug($drugId);

        if ($quantity > 0 && ! $drug->in_stock) {
            throw ApiException::unprocessable('OUT_OF_STOCK', 'drug_id');
        }

        DB::transaction(function () use ($patient, $drug, $quantity) {
            $patient->newQuery()->lockForUpdate()->find($patient->id);

            if ($quantity === 0) {
                CartItem::query()
                    ->where('patient_id', $patient->id)
                    ->where('drug_id', $drug->id)
                    ->delete();

                return;
            }

            $line = CartItem::query()->firstOrNew(['patient_id' => $patient->id, 'drug_id' => $drug->id]);

            $full = ! $line->exists && CartItem::query()->where('patient_id', $patient->id)->count() >= self::MAX_LINES;

            if ($full) {
                throw ApiException::unprocessable('CART_FULL', null, ['limit' => self::MAX_LINES]);
            }

            $line->quantity = $quantity;
            $line->save();
        });

        return $this->show($patient);
    }

    /**
     * The patient's lines whose medicine is still listed, with it and its pharmacy loaded.
     *
     * @return Collection<int, CartItem>
     */
    public function lines(User $patient): Collection
    {
        $listed = $this->storefront
            ->listedDrugs()
            ->select('drugs.id');

        return CartItem::query()
            ->with(['drug.organisation', 'drug.image'])
            ->where('patient_id', $patient->id)
            ->whereIn('drug_id', $listed)
            ->orderBy('id')
            ->limit(self::MAX_LINES)
            ->get();
    }

    /**
     * @param  Collection<int, CartItem>  $lines
     */
    public function price(User $patient, Collection $lines): CartView
    {
        $allowed = array_flip($this->prescribed->names($patient));

        $blocked = $lines
            ->filter(fn(CartItem $line) => $line->drug->requires_prescription && ! isset($allowed[mb_strtolower(trim($line->drug->name))]))
            ->map(fn(CartItem $line) => $line->drug->uuid)
            ->values()
            ->all();

        $pharmacies = Pharmacy::query()
            ->whereIn('organisation_id', $lines->pluck('drug.organisation_id')->unique())
            ->get()
            ->keyBy('organisation_id');

        $delivery = $lines
            ->groupBy('drug.organisation_id')
            ->sum(fn(Collection $group, int $organisationId) => $pharmacies[$organisationId]->deliveryFeeFor($this->subtotal($group)));

        return new CartView($lines, $this->subtotal($lines), $delivery, $blocked);
    }

    /**
     * @param  Collection<int, CartItem>  $lines
     */
    public function subtotal(Collection $lines): int
    {
        return $lines->sum(fn(CartItem $line) => $line->drug->price_kobo * $line->quantity);
    }

    public function clear(User $patient): void
    {
        CartItem::query()
            ->where('patient_id', $patient->id)
            ->delete();
    }

    /**
     * The out-of-stock lines, by medicine id.
     *
     * @param  Collection<int, CartItem>  $lines
     * @return list<string>
     */
    public function outOfStock(Collection $lines): array
    {
        return $lines
            ->reject(fn(CartItem $line) => $line->drug->in_stock)
            ->map(fn(CartItem $line): string => $line->drug->uuid)
            ->values()
            ->all();
    }
}
