<?php

namespace App\Http\Requests\Laboratories;

use App\Http\Requests\ApiRequest;

/**
 * Portal `zSetOfferedTestRequest`; the test code comes from the path.
 */
class SetOfferedTestRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'price_kobo' => ['required', ...LabRules::kobo()],
            'available' => ['required', 'boolean:strict'],
        ];
    }
}
