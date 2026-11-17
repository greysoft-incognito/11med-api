<?php

namespace App\Http\Resources\Doctors;

use App\Http\Resources\ApiResource;
use App\Models\DoctorProfile;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\Request;

/**
 * `zDoctor`: a doctor as a patient sees them. No bank details, documents or review notes.
 *
 * @property DoctorProfile $resource
 */
class DoctorResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->resource;
        $photo = $profile->photo;

        return [
            'id' => $profile->user->uuid,
            'full_name' => $profile->user->full_name,
            'specialty' => $profile->specialty?->value,
            'sub_specialty' => $profile->sub_specialty,
            'years_of_experience' => $profile->years_of_experience ?? 0,
            'current_institution' => $profile->current_institution,
            'languages' => $profile->languages,
            'bio' => $profile->bio ?? '',
            'photo_url' => $photo === null ? null : app(MediaLibrary::class)->url($photo),
            'fee_chat_kobo' => $profile->fee_chat_kobo ?? 0,
            'fee_audio_kobo' => $profile->fee_audio_kobo ?? 0,
            'fee_video_kobo' => $profile->fee_video_kobo ?? 0,
            'rating' => $profile->rating_count === 0 ? null : $profile->rating_mean,
            'rating_count' => $profile->rating_count,
            'is_available_now' => $profile->is_available_now,
            'availability' => $profile->availability,
            'session_minutes' => $profile->session_minutes,
            'chat_window_hours' => $profile->chat_window_hours,
            'follow_up_fee_kobo' => $profile->follow_up_fee_kobo,
        ];
    }
}
