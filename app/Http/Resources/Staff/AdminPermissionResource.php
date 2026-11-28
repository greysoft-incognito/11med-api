<?php

namespace App\Http\Resources\Staff;

use App\Enums\AdminPermission;
use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * `zAdminPermission`.
 *
 * @property AdminPermission $resource
 */
class AdminPermissionResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->resource->value,
            'area' => $this->resource->area(),
        ];
    }
}
