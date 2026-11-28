<?php

namespace App\Http\Requests\Notifications;

use App\Enums\AuthClient;
use App\Enums\NotificationCategory;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zNotificationPreferences`. A category may appear once and must be one the caller is offered on this client;
 * categories left out keep what they had.
 */
class UpdatePreferencesRequest extends ApiRequest
{
    public function rules(): array
    {
        $client = $this->routeIs('v1.portal.*') ? AuthClient::Portal : AuthClient::App;
        $categories = NotificationCategory::valuesFor($this->user(), $client);

        return [
            'preferences' => ['present', 'array', 'max:' . count($categories)],
            'preferences.*' => ['required', 'array:category,push,sms,in_app'],
            'preferences.*.category' => ['required', Rule::in($categories), 'distinct'],
            'preferences.*.push' => ['required', 'boolean:strict'],
            'preferences.*.sms' => ['required', 'boolean:strict'],
            'preferences.*.in_app' => ['required', 'boolean:strict'],
        ];
    }
}
