<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Resources\Pharmacy\DrugCollection;
use App\Http\Resources\Pharmacy\DrugResource;
use App\Http\Resources\Pharmacy\PharmacyCollection;
use App\Http\Resources\Pharmacy\PharmacyResource;
use App\Services\Pharmacy\Storefront;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __construct(
        private readonly Storefront $storefront,
    ) {}

    public function pharmacies(Request $request): PharmacyCollection
    {
        $page = $this->storefront->pharmacies($request->user(), $request->only(['q']), ListQuery::from($request));

        return new PharmacyCollection($page);
    }

    public function pharmacy(string $pharmacyId): PharmacyResource
    {
        $pharmacy = $this->storefront->pharmacy($pharmacyId);

        return new PharmacyResource($pharmacy);
    }

    public function drugs(Request $request): DrugCollection
    {
        $filters = $request->only(['q', 'category', 'in_stock', 'requires_prescription', 'pharmacy_id']);
        $page = $this->storefront->drugs($request->user(), $filters, ListQuery::from($request));

        return new DrugCollection($page);
    }

    public function drug(string $drugId): DrugResource
    {
        $drug = $this->storefront->drug($drugId);

        return new DrugResource($drug);
    }
}
