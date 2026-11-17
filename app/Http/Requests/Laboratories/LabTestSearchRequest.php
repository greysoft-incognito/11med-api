<?php

namespace App\Http\Requests\Laboratories;

use App\Enums\LabTestCategory;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `q` and `category` over the catalogue or a price list.
 */
class LabTestSearchRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category' => ['sometimes', 'nullable', Rule::enum(LabTestCategory::class)],
        ];
    }
}
