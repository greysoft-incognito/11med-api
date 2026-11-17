<?php

namespace App\Http\Resources\Profile;

use App\Http\Resources\ApiResource;
use App\Services\Profile\EmergencyRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The portals' `zEmergencyRecord`: the fixed safe subset, never cached.
 *
 * @property EmergencyRecord $resource
 */
class EmergencyRecordResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $record = $this->resource;
        $profile = $record->profile;

        return [
            'name' => $record->patient->full_name ?: '11med patient',
            'age' => $profile === null ? null : $this->age($profile->date_of_birth),
            'gender' => $profile?->gender->value,
            'blood_type' => $profile?->blood_type->value,
            'genotype' => $profile?->genotype->value,
            'drug_allergies' => array_map(fn(array $allergy) => [
                'drug_name' => $allergy['drug_name'],
                'reaction' => $allergy['reaction_type'],
                'severity' => $allergy['severity'],
            ], $profile->drug_allergies ?? []),
            'medications' => $record->medications,
            'emergency_contact' => $profile === null ? null : [
                'name' => $profile->contact_name,
                'phone' => $profile->contact_phone,
                'relationship' => $profile->contact_relationship->value,
            ],
            'issued_at' => $this->iso($record->token->issued_at),
            'opened_count' => $record->token->opened_count,
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->headers->set('Cache-Control', 'no-store');
    }

    private function age(Carbon $dateOfBirth): int
    {
        $today = now(config('elevenmed.timezone'))->startOfDay();

        return (int) Carbon::parse($dateOfBirth->toDateString(), config('elevenmed.timezone'))->diffInYears($today);
    }
}
