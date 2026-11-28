<?php

namespace App\Http\Resources\Staff;

use App\Http\Resources\ApiCollection;

/**
 * `zPage(zStaffMember)`.
 */
class StaffMemberCollection extends ApiCollection
{
    public $collects = StaffMemberResource::class;
}
