<?php

namespace App\Http\Controllers\Partners;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partners\ApplyPartnerRequest;
use App\Http\Resources\Partners\PartnerResource;
use App\Services\Partners\Organisations;
use Illuminate\Http\JsonResponse;

class PartnerApplicationController extends Controller
{
    public function __construct(
        private readonly Organisations $organisations,
    ) {}

    public function store(ApplyPartnerRequest $request): JsonResponse
    {
        $partner = $this->organisations->apply($request->validated());

        return (new PartnerResource($partner))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }
}
