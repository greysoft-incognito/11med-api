<?php

namespace App\Http\Requests\Support;

use App\Http\Requests\ApiRequest;

/**
 * `zSupportReplyRequest`: the requester may attach a file, the team writes text.
 */
class SupportReplyRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:5000'],
            'attachment_url' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
