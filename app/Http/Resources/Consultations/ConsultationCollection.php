<?php

namespace App\Http\Resources\Consultations;

use App\Http\Resources\ApiCollection;
use App\Services\Consultations\PaymentSummaries;
use Illuminate\Http\Request;

/**
 * The patient's consultations, newest first, paged (FE-003).
 */
class ConsultationCollection extends ApiCollection
{
    public $collects = ConsultationResource::class;

    public function toArray(Request $request): array
    {
        app(PaymentSummaries::class)->prime($this->collection->pluck('resource'));

        return parent::toArray($request);
    }
}
