<?php

namespace App\Http\Requests\Support;

use App\Enums\SupportCategory;
use App\Enums\SupportLinkKind;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zOpenSupportTicketRequest`.
 */
class OpenTicketRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(SupportCategory::class)],
            'subject' => ['required', 'string', 'min:3', 'max:120'],
            'message' => ['required', 'string', 'min:1', 'max:5000'],
            'attachment_url' => ['nullable', 'string', 'max:2048'],
            'link' => ['nullable', 'array'],
            'link.kind' => ['required_with:link', Rule::enum(SupportLinkKind::class)],
            'link.id' => ['required_with:link', 'uuid'],
        ];
    }
}
