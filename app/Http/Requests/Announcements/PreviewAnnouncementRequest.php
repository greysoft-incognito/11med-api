<?php

namespace App\Http\Requests\Announcements;

use App\Enums\AnnouncementAudience;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zAnnouncementPreviewRequest`: how many accounts an audience reaches now.
 */
class PreviewAnnouncementRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'audience' => ['required', Rule::enum(AnnouncementAudience::class)],
        ];
    }
}
