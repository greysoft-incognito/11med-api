<?php

namespace App\Http\Resources\Profile;

use App\Http\Resources\ApiResource;
use App\Models\MedicalProfile;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\Request;

/**
 * `zMedicalProfile`.
 *
 * @property MedicalProfile $resource
 */
class MedicalProfileResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->resource;
        $photo = $profile->photo;

        return [
            'user_id' => $profile->user->uuid,
            'date_of_birth' => $this->day($profile->date_of_birth),
            'gender' => $profile->gender->value,
            'blood_type' => $profile->blood_type->value,
            'genotype' => $profile->genotype->value,
            'state_of_residence' => $profile->state_of_residence,
            'drug_allergies' => $profile->drug_allergies,
            'emergency_contact' => [
                'name' => $profile->contact_name,
                'phone' => $profile->contact_phone,
                'relationship' => $profile->contact_relationship->value,
                'address' => $profile->contact_address,
                'is_verified' => $profile->contact_verified_at !== null,
            ],
            'lga' => $profile->lga,
            'height_cm' => $profile->height_cm,
            'weight_kg' => $profile->weight_kg,
            'profile_photo_url' => $photo === null ? null : app(MediaLibrary::class)->url($photo),
            'existing_conditions' => $profile->existing_conditions,
            'past_surgeries' => $profile->past_surgeries,
            'food_allergies' => $profile->food_allergies,
            'allergy_notes' => $profile->allergy_notes,
            'hmo' => [
                'provider' => $profile->hmo_provider,
                'member_id' => $profile->hmo_member_id,
                'plan_type' => $profile->hmo_plan_type,
                'expiry_date' => $this->day($profile->hmo_expiry_date),
                'nhia_number' => $profile->nhia_number,
            ],
            'updated_at' => $this->iso($profile->updated_at),
        ];
    }
}
