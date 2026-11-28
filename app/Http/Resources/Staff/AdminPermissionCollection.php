<?php

namespace App\Http\Resources\Staff;

use App\Http\Resources\ApiCollection;

/**
 * The permission catalogue, read whole.
 */
class AdminPermissionCollection extends ApiCollection
{
    public $collects = AdminPermissionResource::class;
}
