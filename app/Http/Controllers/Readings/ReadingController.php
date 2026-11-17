<?php

namespace App\Http\Controllers\Readings;

use App\Enums\HttpStatus;
use App\Enums\ReadingType;
use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Readings\ListReadingsRequest;
use App\Http\Requests\Readings\LogReadingRequest;
use App\Http\Resources\EmptyResource;
use App\Http\Resources\Readings\InsightsResource;
use App\Http\Resources\Readings\ReadingCollection;
use App\Http\Resources\Readings\ReadingResource;
use App\Services\Readings\Insights;
use App\Services\Readings\Readings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReadingController extends Controller
{
    public function __construct(
        private readonly Readings $readings,
        private readonly Insights $insights,
    ) {}

    public function index(ListReadingsRequest $request): ReadingCollection
    {
        $patient = $request->user();
        $type = ReadingType::tryFrom((string) $request->validated('type'));
        $window = $this->readings->allowedDays($patient, $request->window());
        $page = $this->readings->of($patient, $type, $window, ListQuery::from($request));

        return new ReadingCollection($page, $window);
    }

    public function store(LogReadingRequest $request): JsonResponse
    {
        $reading = $this->readings->record($request->user(), $request->validated());

        return (new ReadingResource($reading))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }

    public function destroy(Request $request, string $id): EmptyResource
    {
        $this->readings->delete($request->user(), $id);

        return new EmptyResource;
    }

    public function insights(Request $request): InsightsResource
    {
        $insights = $this->insights->for($request->user());

        return new InsightsResource($insights);
    }
}
