<?php

namespace App\Models;

use App\Enums\DosageForm;
use App\Enums\DrugCategory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A medicine on one pharmacy's list (`zDrug`).
 */
class Drug extends Model
{
    public const NOT_FOUND = 'DRUG_NOT_FOUND';

    protected $fillable = [
        'organisation_id',
        'name',
        'brand_name',
        'nafdac_number',
        'category',
        'dosage_form',
        'dosage_strength',
        'description',
        'price_kobo',
        'requires_prescription',
        'in_stock',
        'image_media_id',
    ];

    protected function casts(): array
    {
        return [
            'category' => DrugCategory::class,
            'dosage_form' => DosageForm::class,
            'price_kobo' => 'integer',
            'requires_prescription' => 'boolean',
            'in_stock' => 'boolean',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_media_id');
    }
}
