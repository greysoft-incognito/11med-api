<?php

namespace App\Http\Resources\Notifications;

use App\Http\Resources\ApiResource;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;

/**
 * `zNotificationPreference`.
 *
 * @property NotificationPreference $resource
 */
class NotificationPreferenceResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'category' => $this->resource->category->value,
            'push' => $this->resource->push,
            'sms' => $this->resource->sms,
            'in_app' => $this->resource->in_app,
        ];
    }
}
