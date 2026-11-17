<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A test on one laboratory's price list (`zOfferedTest`): the catalogue test, this laboratory's
 * price, and whether it can run it at the moment.
 */
class LaboratoryTest extends Model
{
    public const NOT_FOUND = 'TEST_NOT_LISTED';

    protected $fillable = [
        'laboratory_id',
        'lab_test_id',
        'price_kobo',
        'available',
    ];

    protected function casts(): array
    {
        return [
            'price_kobo' => 'integer',
            'available' => 'boolean',
        ];
    }

    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class);
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }
}
