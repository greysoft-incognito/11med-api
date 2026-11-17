<?php

namespace App\Http\Requests\Partners;

use App\Enums\PartnerKind;
use App\Http\Requests\ApiRequest;
use App\Support\NigerianGeography;
use App\Support\PhoneNumber;
use Illuminate\Validation\Rule;

/**
 * `zApplyPartnerRequest` (portal `src/api/contracts/admin.ts`).
 */
class ApplyPartnerRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(PartnerKind::class)],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'state' => ['required', 'string', Rule::in(NigerianGeography::states())],
            'city' => ['present', 'nullable', 'string', 'max:80'],
            'address' => ['required', 'string', 'min:5', 'max:200'],
            'licence_number' => ['required', 'string', 'min:4', 'max:40'],
            'licence_url' => ['required', 'string', 'max:2048'],
            'contact_name' => ['required', 'string', 'min:2', 'max:80'],
            'contact_phone' => ['required', 'string', 'regex:' . PhoneNumber::PATTERN],
            'contact_email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
