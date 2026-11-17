<?php

namespace App\Http\Controllers\Medications;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Medications\WindowRequest;
use App\Http\Resources\Medications\ComplianceReportResource;
use App\Http\Resources\Medications\ComplianceResource;
use App\Services\Medications\Compliance;
use App\Services\Medications\ComplianceReports;
use App\Services\Medications\DoseHistory;
use App\Services\Medications\Schedules;
use App\Support\LocalDay;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplianceController extends Controller
{
    public function __construct(
        private readonly Schedules $schedules,
        private readonly DoseHistory $history,
        private readonly Compliance $compliance,
        private readonly ComplianceReports $reports,
    ) {}

    public function show(WindowRequest $request, string $userId): ComplianceResource
    {
        $patient = $this->schedules->readable($request->user(), $userId);
        $day = $request->filled('date') ? LocalDay::of($request->validated('date')) : LocalDay::today();
        $window = $this->history->allowedDays($patient, $request->length('window', 7));
        $compliance = $this->compliance->for($patient, $day, $window);

        return new ComplianceResource($compliance);
    }

    /**
     * The patient's own only: a caregiver reads schedules, never reports.
     */
    public function report(WindowRequest $request, string $userId): ComplianceReportResource
    {
        if ($request->user()->uuid !== $userId) {
            throw ApiException::notFound();
        }

        $report = $this->reports->generate($request->user(), $request->length('window', 30, 7));

        return new ComplianceReportResource($report);
    }

    public function file(string $report): StreamedResponse
    {
        return $this->reports->serve($report);
    }
}
