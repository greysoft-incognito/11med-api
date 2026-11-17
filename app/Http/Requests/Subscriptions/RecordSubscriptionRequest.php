<?php

namespace App\Http\Requests\Subscriptions;

use App\Http\Requests\ApiRequest;
use App\Http\Requests\Identity\IdentityRules;
use App\Models\Subscription;

/**
 * `zRecordSubscriptionRequest`; the end date must be after today in Lagos.
 */
class RecordSubscriptionRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'phone' => IdentityRules::phone(),
            'premium_until' => ['required', 'date_format:Y-m-d', 'after:' . Subscription::today()],
            'reference' => ['required', 'string', 'min:4', 'max:64'],
        ];
    }
}
