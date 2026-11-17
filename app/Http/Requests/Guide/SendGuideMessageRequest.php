<?php

namespace App\Http\Requests\Guide;

use App\Http\Requests\ApiRequest;

/**
 * `zGuideSendRequest`: 1 to 1000 characters, trimmed.
 */
class SendGuideMessageRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => __('guide.say_what_you_need'),
        ];
    }
}
