<?php

namespace App\Http\Controllers\Laboratories;

use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Resources\Laboratories\LabRequestCollection;
use App\Http\Resources\Laboratories\LabRequestResource;
use App\Services\Laboratories\LabRequests;
use Illuminate\Http\Request;

/**
 * A doctor's test requests, as the patient reads them.
 */
class LabRequestController extends Controller
{
    public function __construct(
        private readonly LabRequests $requests,
    ) {}

    public function index(Request $request): LabRequestCollection
    {
        $page = $this->requests->of($request->user(), ListQuery::from($request));

        return new LabRequestCollection($page);
    }

    public function show(Request $request, string $requestId): LabRequestResource
    {
        $labRequest = $this->requests->find($request->user(), $requestId);

        return new LabRequestResource($labRequest);
    }
}
