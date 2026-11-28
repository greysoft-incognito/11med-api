<?php

namespace App\Http\Requests\Support;

use App\Enums\SupportCategory;
use App\Enums\SupportStatus;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * The queue's filters.
 */
class SupportQueueRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(SupportStatus::class)],
            'category' => ['nullable', Rule::enum(SupportCategory::class)],
            'assigned' => ['nullable', Rule::in(['me', 'unassigned'])],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }
}
