<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of a patient's cart: working state, removed for real when the patient takes it out.
 */
class CartItem extends Eloquent
{
    use HasPublicId;

    protected $fillable = [
        'patient_id',
        'drug_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }
}
