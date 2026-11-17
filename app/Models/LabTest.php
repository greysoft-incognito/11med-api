<?php

namespace App\Models;

use App\Enums\LabTestCategory;
use App\Enums\SampleType;

/**
 * A test in the platform's catalogue (`zLabTest`). The code is its identity everywhere.
 */
class LabTest extends Model
{
    public const NOT_FOUND = 'TEST_NOT_FOUND';

    protected $fillable = [
        'code',
        'name',
        'category',
        'sample',
        'description',
        'fasting_hours',
        'turnaround_hours',
    ];

    protected function casts(): array
    {
        return [
            'category' => LabTestCategory::class,
            'sample' => SampleType::class,
            'fasting_hours' => 'integer',
            'turnaround_hours' => 'integer',
        ];
    }
}
