<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class StaffSearchRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', Rule::in(['invited', 'active', 'deactivated'])],
            'role' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
