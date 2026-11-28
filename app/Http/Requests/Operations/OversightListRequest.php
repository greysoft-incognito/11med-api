<?php

namespace App\Http\Requests\Operations;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * An oversight list's filters: `status` (one of the record's statuses, or `open`) and `q`.
 */
abstract class OversightListRequest extends ApiRequest
{
    /**
     * @return list<string>
     */
    abstract protected function statuses(): array;

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['open', ...$this->statuses()])],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }
}
