<?php

namespace App\Http\Resources\Staff;

use App\Http\Resources\ApiCollection;

/**
 * Every staff role, a short list read whole.
 */
class StaffRoleCollection extends ApiCollection
{
    public $collects = StaffRoleResource::class;
}
