<?php

namespace App\Http\Resources\Doctors;

use App\Http\Resources\ApiResource;
use App\Models\DoctorProfile;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\Request;

/**
 * `zDoctorProfile`: the doctor's own record, bank account masked.
 *
 * @property DoctorProfile $resource
 */
class DoctorProfileResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->resource;
        $photo = $profile->photo;

        return [
            'user_id' => $profile->user->uuid,
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
            'session_minutes' => $profile->session_minutes,
            'chat_window_hours' => $profile->chat_window_hours,
            'follow_up_fee_kobo' => $profile->follow_up_fee_kobo,
            'bank_name' => $profile->bank_name,
            'bank_account_masked' => $profile->bankAccountMasked(),
            'availability' => $profile->availability,
            'is_available_now' => $profile->is_available_now,
            'verification_status' => $profile->verification_status->value,
            'verification_notes' => $profile->verification_notes,
            'submitted_at' => $this->iso($profile->submitted_at),
            'reviewed_at' => $this->iso($profile->reviewed_at),
            'documents' => DocumentRows::of($profile),
        ];
    }
}
