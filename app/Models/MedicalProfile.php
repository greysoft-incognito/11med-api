<?php

namespace App\Models;

use App\Enums\BloodType;
use App\Enums\Gender;
use App\Enums\Genotype;
use App\Enums\Relationship;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A patient's medical profile (app spec §3, `zMedicalProfile`). Medical data: never logged.
 */
class MedicalProfile extends Model
{
    public const NOT_FOUND = 'PROFILE_NOT_FOUND';

    protected $fillable = [
        'user_id',
        'date_of_birth',
        'gender',
        'blood_type',
        'genotype',
        'state_of_residence',
        'lga',
        'drug_allergies',
        'contact_name',
        'contact_phone',
        'contact_relationship',
        'contact_address',
        'contact_verified_at',
        'height_cm',
        'weight_kg',
        'photo_media_id',
        'existing_conditions',
        'past_surgeries',
        'food_allergies',
        'allergy_notes',
        'hmo_provider',
        'hmo_member_id',
        'hmo_plan_type',
        'hmo_expiry_date',
        'nhia_number',
    ];

    protected $attributes = [
        'drug_allergies' => '[]',
        'existing_conditions' => '[]',
        'past_surgeries' => '[]',
        'food_allergies' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date:Y-m-d',
            'gender' => Gender::class,
            'blood_type' => BloodType::class,
            'genotype' => Genotype::class,
            'drug_allergies' => 'array',
            'contact_relationship' => Relationship::class,
            'contact_verified_at' => 'datetime',
            'height_cm' => 'float',
            'weight_kg' => 'float',
            'existing_conditions' => 'array',
            'past_surgeries' => 'array',
            'food_allergies' => 'array',
            'hmo_expiry_date' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'photo_media_id');
    }
}
