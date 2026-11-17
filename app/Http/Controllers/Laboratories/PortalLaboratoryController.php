<?php

namespace App\Http\Controllers\Laboratories;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Laboratories\LabTestSearchRequest;
use App\Http\Requests\Laboratories\SetOfferedTestRequest;
use App\Http\Requests\Laboratories\UpdateLaboratoryRequest;
use App\Http\Resources\EmptyResource;
use App\Http\Resources\Laboratories\CatalogueEntryCollection;
use App\Http\Resources\Laboratories\LaboratoryResource;
use App\Http\Resources\Laboratories\LaboratorySummaryResource;
use App\Http\Resources\Laboratories\OfferedTestCollection;
use App\Http\Resources\Laboratories\OfferedTestResource;
use App\Services\Laboratories\Laboratories;
use App\Services\Laboratories\PriceLists;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The laboratory portal: the listing, the summary and the price list (portal handover §5).
 */
class PortalLaboratoryController extends Controller
{
    public function __construct(
        private readonly Laboratories $laboratories,
        private readonly PriceLists $priceLists,
    ) {}

    public function show(Request $request): LaboratoryResource
    {
        $laboratory = $this->laboratories->of($request->user());

        return new LaboratoryResource($laboratory);
    }

    public function update(UpdateLaboratoryRequest $request): LaboratoryResource
    {
        $laboratory = $this->laboratories->update($request->user(), $request->validated());

        return new LaboratoryResource($laboratory);
    }

    public function summary(Request $request): LaboratorySummaryResource
    {
        $laboratory = $this->laboratories->of($request->user());
        $summary = $this->laboratories->summary($laboratory);

        return new LaboratorySummaryResource($summary);
    }

    public function catalogue(LabTestSearchRequest $request): CatalogueEntryCollection
    {
        $laboratory = $this->laboratories->of($request->user());
        $entries = $this->priceLists->catalogueFor($laboratory, $request->validated('q'), $request->validated('category'));

        return new CatalogueEntryCollection($entries);
    }

    public function tests(LabTestSearchRequest $request): OfferedTestCollection
    {
        $laboratory = $this->laboratories->of($request->user());
        $tests = $this->priceLists->of($laboratory, $request->validated('q'), $request->validated('category'));

        return new OfferedTestCollection($tests);
    }

    public function setTest(SetOfferedTestRequest $request, string $code): JsonResponse
    {
        $laboratory = $this->laboratories->of($request->user());

        [$entry, $created] = $this->priceLists->set(
            $laboratory,
            $code,
            $request->integer('price_kobo'),
            $request->boolean('available')
        );

        return (new OfferedTestResource($entry))
            ->response()
            ->setStatusCode($created ? HttpStatus::CREATED->value : HttpStatus::OK->value);
    }

    public function removeTest(Request $request, string $code): EmptyResource
    {
        $laboratory = $this->laboratories->of($request->user());

        $this->priceLists->remove($laboratory, $code);

        return new EmptyResource;
    }
}
