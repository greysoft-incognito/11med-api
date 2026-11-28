<?php

namespace App\Http\Requests\Accounts;

use App\Enums\Role;
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
            'role' => ['nullable', Rule::in(Role::values(Role::app()))],
            'status' => ['nullable', Rule::in([UserDirectory::ACTIVE, UserDirectory::SUSPENDED])],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }
}
