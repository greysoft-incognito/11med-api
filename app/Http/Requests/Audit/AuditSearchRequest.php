<?php

namespace App\Http\Requests\Audit;

use App\Http\Requests\ApiRequest;

class AuditSearchRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'actor' => ['sometimes', 'nullable', 'uuid'],
            'action' => ['sometimes', 'nullable', 'string', 'max:64'],
            'subject_type' => ['sometimes', 'nullable', 'string', 'max:40'],
            'subject_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
