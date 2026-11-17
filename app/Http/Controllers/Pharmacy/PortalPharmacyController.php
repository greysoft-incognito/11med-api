<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\UpdatePharmacyRequest;
use App\Http\Resources\Pharmacy\PharmacyResource;
use App\Http\Resources\Pharmacy\PharmacySummaryResource;
use App\Services\Pharmacy\PharmacyStore;
use Illuminate\Http\Request;

class PortalPharmacyController extends Controller
{
    public function __construct(
        private readonly PharmacyStore $store,
    ) {}

    public function show(Request $request): PharmacyResource
    {
        $pharmacy = $this->store->show($request->user());

        return new PharmacyResource($pharmacy);
    }

    public function update(UpdatePharmacyRequest $request): PharmacyResource
    {
        $pharmacy = $this->store->update($request->user(), $request->validated());

        return new PharmacyResource($pharmacy);
    }

    public function summary(Request $request): PharmacySummaryResource
    {
        $summary = $this->store->summary($request->user());

        return new PharmacySummaryResource($summary);
    }
}
