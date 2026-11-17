<?php

namespace App\Services\Pharmacy;

use App\Models\CartItem;
use Illuminate\Support\Collection;

/**
 * A priced cart (`zCart`).
 */
final readonly class CartView
{
    /**
     * @param  Collection<int, CartItem>  $lines
     * @param  list<string>  $blocked  Prescription-only medicines with no valid prescription behind them.
     */
    public function __construct(
        public Collection $lines,
        public int $subtotalKobo,
        public int $deliveryFeeKobo,
        public array $blocked,
    ) {}

    public function totalKobo(): int
    {
        return $this->subtotalKobo + $this->deliveryFeeKobo;
    }
}
