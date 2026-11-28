<?php

namespace App\Http\Resources\Operations;

use App\Http\Resources\ApiCollection;

/**
 * Every consultation, for the team, paged.
 */
class AdminConsultationCollection extends ApiCollection
{
    public $collects = AdminConsultationResource::class;
}
