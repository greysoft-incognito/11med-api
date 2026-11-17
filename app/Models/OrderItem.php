<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of an order: the medicine as sold, its name, strength and price at checkout.
 */
class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'drug_id',
        'drug_name',
        'dosage_strength',
        'price_kobo',
        'quantity',
        'requires_prescription',
    ];

    protected function casts(): array
    {
        return [
            'price_kobo' => 'integer',
            'quantity' => 'integer',
            'requires_prescription' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class)->withTrashed();
    }
}
