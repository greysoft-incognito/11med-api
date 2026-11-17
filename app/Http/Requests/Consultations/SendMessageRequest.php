<?php

namespace App\Http\Requests\Consultations;

use App\Http\Requests\ApiRequest;

/**
 * `zSendMessageRequest`: words, an attachment, or both; empty words need an attachment.
 */
class SendMessageRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:2000', 'required_without:attachment_url'],
            'attachment_url' => ['nullable', 'url'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $body = $this->input('body');

        $this->merge(['body' => is_string($body) && trim($body) === '' ? null : $body]);
    }
}
