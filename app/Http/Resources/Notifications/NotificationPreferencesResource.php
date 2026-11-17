<?php

namespace App\Http\Resources\Notifications;

use App\Http\Resources\ApiResource;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * `zNotificationPreferences`: every category, saved or defaulted.
 *
 * @property Collection<int, NotificationPreference> $resource
 */
class NotificationPreferencesResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'preferences' => NotificationPreferenceResource::collection($this->resource)->resolve($request),
        ];
    }
}
