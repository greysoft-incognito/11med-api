<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\ApiRequest;

/**
 * `zDeleteStaffRoleRequest`: the role its holders move to, when it has any.
 */
class DeleteStaffRoleRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'reassign_to' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
