<?php

namespace App\Http\Requests\Notifications;

use App\Enums\NotificationCategory;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zNotificationPreferences`. A category may appear once; categories left out keep what they had.
 */
class UpdatePreferencesRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'preferences' => ['present', 'array', 'max:' . count(NotificationCategory::cases())],
            'preferences.*' => ['required', 'array:category,push,sms,in_app'],
            'preferences.*.category' => ['required', Rule::enum(NotificationCategory::class), 'distinct'],
            'preferences.*.push' => ['required', 'boolean:strict'],
            'preferences.*.sms' => ['required', 'boolean:strict'],
            'preferences.*.in_app' => ['required', 'boolean:strict'],
        ];
    }
}
