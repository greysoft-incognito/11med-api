<?php

namespace App\Services\Laboratories;

use App\Enums\NotificationCategory;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\LabRequest;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Database\QueryException;

/**
 * A doctor's test requests (app spec §11.4): a recommendation, one per consultation, that the
 * patient books at a laboratory of their choice. The Consultations module issues them through
 * `create()`; the patient reads them here.
 */
class LabRequests
{
    public function __construct(
        private readonly Catalogue $catalogue,
        private readonly Notifier $notifier,
    ) {}

    /**
     * Issue a request on a consultation the caller has already checked the doctor owns. Each
     * code must be in the catalogue (422 `UNKNOWN_TEST`); one per consultation (409
     * `LAB_REQUEST_EXISTS`). The patient is told; the push names no test.
     *
     * @param  list<array{test_code: string, reason?: string|null}>  $tests
     */
    public function create(User $doctor, User $patient, string $consultationId, string $mdcnNumber, array $tests, ?string $notes): LabRequest
    {
        if (LabRequest::query()->where('consultation_id', $consultationId)->exists()) {
            throw ApiException::conflict('LAB_REQUEST_EXISTS');
        }

        $known = $this->catalogue->byCodes(array_column($tests, 'test_code'));
        $requested = [];

        foreach ($tests as $index => $test) {
            $code = strtoupper($test['test_code']);
            $catalogued = $known->get($code);

            if ($catalogued === null) {
                throw ApiException::unprocessable('UNKNOWN_TEST', "tests.{$index}.test_code", ['test_code' => $code]);
            }

            $requested[$code] = [
                'test_code' => $code,
                'test_name' => $catalogued->name,
                'reason' => $test['reason'] ?? null,
            ];
        }

        try {
            $request = LabRequest::create([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'consultation_id' => $consultationId,
                'doctor_name' => $doctor->full_name,
                'mdcn_number' => $mdcnNumber,
                'tests' => array_values($requested),
                'notes' => $notes,
                'issued_at' => now(),
            ]);
        } catch (QueryException $e) {
            throw $e->getCode() === '23505' ? ApiException::conflict('LAB_REQUEST_EXISTS') : $e;
        }

        $this->notifier->send(
            $patient,
            NotificationCategory::LabRequest,
            __('labs.push.request_title'),
            __('labs.push.request_body', ['doctor' => $doctor->full_name]),
            ['target_id' => $request->uuid]
        );

        return $request->setRelation('booking', null);
    }

    /**
     * The request on a consultation; 404 `NO_LAB_REQUEST` when none was issued.
     */
    public function forConsultation(string $consultationId): LabRequest
    {
        $request = LabRequest::query()
            ->with('booking')
            ->where('consultation_id', $consultationId)
            ->first();

        if ($request === null) {
            throw ApiException::notFound('NO_LAB_REQUEST');
        }

        return $request;
    }

    /**
     * The patient's requests, newest first.
     */
    public function of(User $patient, ListQuery $list): Page
    {
        $query = LabRequest::query()
            ->with('booking')
            ->where('patient_id', $patient->id)
            ->orderByDesc('issued_at')
            ->orderByDesc('id');

        return $list->paginate($query);
    }

    /**
     * One of the patient's requests; anyone else's is 404 `LAB_REQUEST_NOT_FOUND`.
     */
    public function find(User $patient, string $requestId, bool $lock = false): LabRequest
    {
        $request = LabRequest::query()
            ->where('patient_id', $patient->id)
            ->where('uuid', $requestId)
            ->when($lock, fn($q) => $q->lockForUpdate())
            ->first();

        if ($request === null) {
            throw ApiException::notFound(LabRequest::NOT_FOUND);
        }

        return $request->load('booking');
    }
}
