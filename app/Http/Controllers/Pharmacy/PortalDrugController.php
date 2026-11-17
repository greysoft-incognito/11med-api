<?php

namespace App\Http\Controllers\Pharmacy;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Pharmacy\UpsertDrugRequest;
use App\Http\Resources\EmptyResource;
use App\Http\Resources\Pharmacy\DrugCollection;
use App\Http\Resources\Pharmacy\DrugResource;
use App\Services\Pharmacy\DrugShelf;
use App\Services\Pharmacy\PharmacyStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalDrugController extends Controller
{
    public function __construct(
        private readonly PharmacyStore $store,
        private readonly DrugShelf $shelf,
    ) {}

    public function index(Request $request): DrugCollection
    {
        $pharmacy = $this->store->of($request->user());
        $page = $this->shelf->list($pharmacy, $request->only(['q', 'category', 'stock']), ListQuery::from($request));

        return new DrugCollection($page);
    }

    public function show(Request $request, string $id): DrugResource
    {
        $pharmacy = $this->store->of($request->user());
        $drug = $this->shelf->find($pharmacy, $id);

        return new DrugResource($drug);
    }

    public function store(UpsertDrugRequest $request): JsonResponse
    {
        $pharmacy = $this->store->of($request->user());
        $drug = $this->shelf->create($request->user(), $pharmacy, $request->validated());

        return (new DrugResource($drug))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }

    public function update(UpsertDrugRequest $request, string $id): DrugResource
    {
        $pharmacy = $this->store->of($request->user());
        $drug = $this->shelf->update($request->user(), $pharmacy, $id, $request->validated());

        return new DrugResource($drug);
    }

    public function destroy(Request $request, string $id): EmptyResource
    {
        $pharmacy = $this->store->of($request->user());
        $this->shelf->delete($pharmacy, $id);

        return new EmptyResource;
    }
}
