<?php

namespace App\Http\Requests\Profile;

use App\Enums\AllergySeverity;
use App\Enums\BloodType;
use App\Enums\Gender;
use App\Enums\Genotype;
use App\Enums\Relationship;
use App\Support\NigerianGeography;
use App\Support\PhoneNumber;
use Illuminate\Validation\Rule;

/**
 * The rules `zMedicalProfile`'s request schemas share (app `src/api/contracts/profile.ts`). On an
 * update every key is optional, and a present core field may not be null.
 */
final class ProfileRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function core(bool $partial): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'date_of_birth' => [...$required, 'date_format:Y-m-d', 'before_or_equal:today'],
            'gender' => [...$required, Rule::enum(Gender::class)],
            'blood_type' => [...$required, Rule::enum(BloodType::class)],
            'genotype' => [...$required, Rule::enum(Genotype::class)],
            'state_of_residence' => [...$required, 'string', Rule::in(NigerianGeography::states())],
            'lga' => ['sometimes', 'nullable', 'string', 'max:60'],
            'drug_allergies' => [$partial ? 'sometimes' : 'present', 'array', 'max:50'],
            'drug_allergies.*.drug_name' => ['required', 'string', 'max:120'],
            'drug_allergies.*.reaction_type' => ['nullable', 'string', 'max:255'],
            'drug_allergies.*.severity' => ['required', Rule::enum(AllergySeverity::class)],
            'emergency_contact' => [...$required, 'array'],
            'emergency_contact.name' => ['required_with:emergency_contact', 'string', 'min:2', 'max:120'],
            'emergency_contact.phone' => ['required_with:emergency_contact', 'string', 'regex:' . PhoneNumber::PATTERN],
            'emergency_contact.relationship' => ['required_with:emergency_contact', Rule::enum(Relationship::class)],
            'emergency_contact.address' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The progressive fields, asked for in context (spec §3.2).
     *
     * @return array<string, list<mixed>>
     */
    public static function progressive(): array
    {
        return [
            'height_cm' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:300'],
            'weight_kg' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:700'],
            'profile_photo_url' => ['sometimes', 'nullable', 'string', 'url', 'max:2048'],
            'existing_conditions' => ['sometimes', 'array', 'max:50'],
            'existing_conditions.*' => ['required', 'string', 'max:120'],
            'past_surgeries' => ['sometimes', 'array', 'max:50'],
            'past_surgeries.*.surgery_name' => ['required', 'string', 'max:120'],
            'past_surgeries.*.date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'past_surgeries.*.hospital' => ['nullable', 'string', 'max:160'],
            'food_allergies' => ['sometimes', 'array', 'max:50'],
            'food_allergies.*' => ['required', 'string', 'max:120'],
            'allergy_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'hmo' => ['sometimes', 'array'],
            'hmo.provider' => ['sometimes', 'nullable', 'string', 'max:120'],
            'hmo.member_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'hmo.plan_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'hmo.expiry_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'hmo.nhia_number' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
