<?php

namespace App\Http\Resources\Consultations;

use App\Http\Resources\ApiCollection;

/**
 * A consultation's chat, oldest first, paged (FE-101).
 */
class ConsultationMessageCollection extends ApiCollection
{
    public $collects = ConsultationMessageResource::class;
}
