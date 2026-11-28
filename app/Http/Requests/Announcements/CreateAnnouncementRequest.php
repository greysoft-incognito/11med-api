<?php

namespace App\Http\Requests\Announcements;

use App\Enums\AnnouncementAudience;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zCreateAnnouncementRequest` (portal `src/api/contracts/operations.ts`): sent now when `send_at`
 * is null, otherwise at that time, within ninety days.
 */
class CreateAnnouncementRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'body' => ['required', 'string', 'min:3', 'max:1000'],
            'audience' => ['required', Rule::enum(AnnouncementAudience::class)],
            'push' => ['required', 'boolean'],
            'send_at' => ['present', 'nullable', 'date', 'after:now', 'before:' . now()->addDays(90)->toIso8601String()],
        ];
    }
}
