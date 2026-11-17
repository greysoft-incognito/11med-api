<?php

namespace App\Http\Requests\Laboratories;

use App\Enums\LabTestCategory;
use App\Enums\SampleType;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * Portal `zUpsertLabTestRequest`: the team adding or editing a catalogue test.
 */
class UpsertLabTestRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['code', 'name', 'description'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim($this->input($key))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^[A-Z0-9_]{2,16}$/'],
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'category' => ['required', Rule::enum(LabTestCategory::class)],
            'sample' => ['required', Rule::enum(SampleType::class)],
            'description' => ['required', 'string', 'min:10', 'max:300'],
            'fasting_hours' => ['required', 'integer', 'min:0', 'max:24'],
            'turnaround_hours' => ['required', 'integer', 'min:1', 'max:' . 24 * 14],
        ];
    }
}
