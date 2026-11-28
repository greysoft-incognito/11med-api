<?php

namespace App\Http\Requests\Identity;

use App\Enums\AccountKind;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zAddKindRequest`: the app side to open on the signed-in account.
 */
class AddKindRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(AccountKind::values(AccountKind::app()))],
        ];
    }
}
