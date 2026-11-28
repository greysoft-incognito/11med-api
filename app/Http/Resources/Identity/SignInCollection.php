<?php

namespace App\Http\Resources\Identity;

use App\Http\Resources\ApiCollection;

/**
 * `zSignedInSessionList`, paged.
 */
class SignInCollection extends ApiCollection
{
    public $collects = SignInResource::class;
}
