<?php

namespace App\Http\Requests\Laboratories;

use App\Http\Requests\ApiRequest;
use App\Support\NigerianGeography;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Portal `zUpdateLaboratoryRequest`. Closing must come after opening (400 under `closes`).
 */
class UpdateLaboratoryRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['name', 'city', 'address'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim($this->input($key))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:150'],
            'state' => ['required', 'string', Rule::in(NigerianGeography::states())],
            'city' => ['present', 'nullable', 'string', 'max:80'],
            'address' => ['present', 'nullable', 'string', 'max:255'],
            'logo_url' => ['present', 'nullable', 'string', 'max:2048'],
            'cover_url' => ['present', 'nullable', 'string', 'max:2048'],
            'opens' => LabRules::timeOfDay(),
            'closes' => LabRules::timeOfDay(),
            'home_collection_fee_kobo' => ['present', 'nullable', ...LabRules::kobo()],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $data = $validator->getData();

                if (! $validator->errors()->hasAny(['opens', 'closes']) && $data['closes'] <= $data['opens']) {
                    $validator->errors()->add('closes', __('errors.CLOSES_BEFORE_OPENS'));
                }
            },
        ];
    }
}
