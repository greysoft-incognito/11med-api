<?php

namespace App\Http\Resources\Accounts;

use App\Http\Resources\ApiCollection;

/**
 * The team's user directory, paged.
 */
class AdminUserCollection extends ApiCollection
{
    public $collects = AdminUserResource::class;
}
