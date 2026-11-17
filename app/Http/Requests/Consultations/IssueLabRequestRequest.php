<?php

namespace App\Http\Requests\Consultations;

use App\Http\Requests\ApiRequest;

/**
 * App `zIssueLabRequestRequest`.
 */
class IssueLabRequestRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'tests' => ['required', 'array', 'min:1', 'max:30', 'list'],
            'tests.*.test_code' => ['required', 'string', 'min:1', 'max:16'],
            'tests.*.reason' => ['sometimes', 'nullable', 'string', 'max:200'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
