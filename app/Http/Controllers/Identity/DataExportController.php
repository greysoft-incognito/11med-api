<?php

namespace App\Http\Controllers\Identity;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EmptyResource;
use App\Http\Resources\Identity\DataExportResource;
use App\Services\Identity\Export\DataExports;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download my data (NDPR right of access).
 */
class DataExportController extends Controller
{
    public function __construct(
        private readonly DataExports $exports,
    ) {}

    /**
     * The latest export, or `data: null` before the first request.
     */
    public function show(Request $request): DataExportResource|EmptyResource
    {
        $export = $this->exports->latest($request->user());

        return $export === null ? new EmptyResource : new DataExportResource($export);
    }

    public function store(Request $request): JsonResponse
    {
        $export = $this->exports->request($request->user());

        return (new DataExportResource($export))
            ->response()
            ->setStatusCode(HttpStatus::ACCEPTED->value);
    }

    public function file(string $export): StreamedResponse
    {
        return $this->exports->serve($export);
    }
}
