<?php

namespace App\Http\Resources\Doctors;

use App\Http\Resources\ApiResource;
use App\Models\DoctorProfile;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\Request;

/**
 * `zDoctorApplication` (portal `admin.ts`): the application as the team reviews it, each document
 * with a signed link that lapses.
 *
 * @property DoctorProfile $resource
 */
class DoctorApplicationResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->resource;
        $photo = $profile->photo;

        return [
            'user_id' => $profile->user->uuid,
            'full_name' => $profile->user->full_name,
            'phone' => $profile->user->phone,
            'email' => $profile->user->email,
            'mdcn_number' => $profile->mdcn_number,
            'specialty' => $profile->specialty?->value,
            'sub_specialty' => $profile->sub_specialty,
            'years_of_experience' => $profile->years_of_experience,
            'current_institution' => $profile->current_institution,
            'languages' => $profile->languages,
            'bio' => $profile->bio,
            'photo_url' => $photo === null ? null : app(MediaLibrary::class)->url($photo),
            'fee_chat_kobo' => $profile->fee_chat_kobo,
            'fee_audio_kobo' => $profile->fee_audio_kobo,
            'fee_video_kobo' => $profile->fee_video_kobo,
            'verification_status' => $profile->verification_status->value,
            'verification_notes' => $profile->verification_notes,
            'submitted_at' => $this->iso($profile->submitted_at),
            'reviewed_at' => $this->iso($profile->reviewed_at),
            'documents' => DocumentRows::of($profile, withUrl: true),
        ];
    }
}
