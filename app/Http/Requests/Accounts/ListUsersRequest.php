<?php

namespace App\Http\Requests\Accounts;

use App\Enums\AccountKind;
use App\Http\Requests\ApiRequest;
use App\Services\Accounts\UserDirectory;
use Illuminate\Validation\Rule;

/**
 * The user directory's filters (portal `zUserListQuery`).
 */
class ListUsersRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'kind' => ['nullable', Rule::in(AccountKind::values(AccountKind::app()))],
            'status' => ['nullable', Rule::in([UserDirectory::ACTIVE, UserDirectory::SUSPENDED])],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }
}
