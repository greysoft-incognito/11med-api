<?php

namespace App\Http\Controllers\Laboratories;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Laboratories\LabTestSearchRequest;
use App\Http\Requests\Laboratories\UpsertLabTestRequest;
use App\Http\Resources\Laboratories\LabTestCollection;
use App\Http\Resources\Laboratories\LabTestResource;
use App\Services\Laboratories\Catalogue;
use Illuminate\Http\JsonResponse;

/**
 * The team's test catalogue (portal handover §6 "The test catalogue"). There is no delete.
 */
class CatalogueController extends Controller
{
    public function __construct(
        private readonly Catalogue $catalogue,
    ) {}

    public function index(LabTestSearchRequest $request): LabTestCollection
    {
        $tests = $this->catalogue->all($request->validated('q'), $request->validated('category'));

        return new LabTestCollection($tests);
    }

    public function store(UpsertLabTestRequest $request): JsonResponse
    {
        $test = $this->catalogue->create($request->validated());

        return (new LabTestResource($test))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }

    public function update(UpsertLabTestRequest $request, string $code): LabTestResource
    {
        $test = $this->catalogue->update($code, $request->validated());

        return new LabTestResource($test);
    }
}
