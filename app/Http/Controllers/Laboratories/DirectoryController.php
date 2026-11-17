<?php

namespace App\Http\Controllers\Laboratories;

use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Laboratories\LabTestSearchRequest;
use App\Http\Resources\Laboratories\CatalogueTestCollection;
use App\Http\Resources\Laboratories\LaboratoryCollection;
use App\Http\Resources\Laboratories\LaboratoryResource;
use App\Http\Resources\Laboratories\OfferedTestCollection;
use App\Services\Laboratories\Catalogue;
use App\Services\Laboratories\Directory;
use App\Services\Laboratories\PriceLists;
use App\Services\Profile\MedicalProfiles;
use Illuminate\Http\Request;

/**
 * The app's laboratory directory and the catalogue (app handover §10).
 */
class DirectoryController extends Controller
{
    public function __construct(
        private readonly Directory $directory,
        private readonly Catalogue $catalogue,
        private readonly PriceLists $priceLists,
        private readonly MedicalProfiles $profiles,
    ) {}

    public function index(Request $request): LaboratoryCollection
    {
        $page = $this->directory->search(
            $this->profiles->stateOf($request->user()),
            $request->string('q')->toString(),
            $request->string('test')->toString(),
            ListQuery::from($request)
        );

        return new LaboratoryCollection($page);
    }

    public function catalogue(LabTestSearchRequest $request): CatalogueTestCollection
    {
        $tests = $this->catalogue->withPrices($request->validated('q'), $request->validated('category'));

        return new CatalogueTestCollection($tests);
    }

    public function show(string $labId): LaboratoryResource
    {
        $laboratory = $this->directory->find($labId);

        return new LaboratoryResource($laboratory);
    }

    public function tests(LabTestSearchRequest $request, string $labId): OfferedTestCollection
    {
        $laboratory = $this->directory->find($labId);
        $tests = $this->priceLists->of($laboratory, $request->validated('q'), $request->validated('category'));

        return new OfferedTestCollection($tests);
    }
}
