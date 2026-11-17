<?php

namespace App\Http\Controllers\Medications;

use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Medications\LogDoseRequest;
use App\Http\Requests\Medications\LogDosesBatchRequest;
use App\Http\Requests\Medications\WindowRequest;
use App\Http\Resources\EmptyResource;
use App\Http\Resources\Medications\DoseCollection;
use App\Services\Medications\DoseBook;
use App\Services\Medications\DoseHistory;
use App\Services\Medications\DoseLogger;
use App\Services\Medications\Schedules;
use App\Support\LocalDay;
use Illuminate\Http\Request;

class DoseController extends Controller
{
    public function __construct(
        private readonly Schedules $schedules,
        private readonly DoseBook $doses,
        private readonly DoseHistory $history,
        private readonly DoseLogger $logger,
    ) {}

    public function day(WindowRequest $request, string $userId): DoseCollection
    {
        $patient = $this->schedules->readable($request->user(), $userId);
        $day = $request->filled('date') ? LocalDay::of($request->validated('date')) : LocalDay::today();
        $doses = $this->doses->day($patient, $day);

        return new DoseCollection($doses);
    }

    public function history(WindowRequest $request, string $userId): DoseCollection
    {
        $patient = $this->schedules->readable($request->user(), $userId);
        $days = $this->history->allowedDays($patient, $request->length('days', config('elevenmed.free_tier.history_days')));
        $page = $this->history->of($patient, $days, $request->validated('medication_id'), ListQuery::from($request));

        return new DoseCollection($page);
    }

    public function log(LogDoseRequest $request): EmptyResource
    {
        $this->logger->log($request->user(), $request->validated());

        return new EmptyResource;
    }

    public function logBatch(LogDosesBatchRequest $request): EmptyResource
    {
        $this->logger->logBatch($request->user(), $request->validated('entries'));

        return new EmptyResource;
    }

    public function retract(Request $request, string $doseId): EmptyResource
    {
        $this->logger->retract($request->user(), $doseId);

        return new EmptyResource;
    }
}
