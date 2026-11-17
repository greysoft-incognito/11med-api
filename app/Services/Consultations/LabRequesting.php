<?php

namespace App\Services\Consultations;

use App\Enums\ConsultationStatus;
use App\Exceptions\ApiException;
use App\Models\Consultation;
use App\Models\DoctorProfile;
use App\Models\LabRequest;
use App\Models\User;
use App\Services\Laboratories\LabRequests;

/**
 * A doctor's request for tests from a session (app spec §11.4): one per consultation, once the
 * session has started. The Laboratories module keeps the request and tells the patient.
 */
class LabRequesting
{
    public function __construct(
        private readonly LabRequests $requests,
    ) {}

    /**
     * @param  array{tests: list<array{test_code: string, reason?: ?string}>, notes?: ?string}  $data
     */
    public function issue(Consultation $consultation, User $doctor, array $data): LabRequest
    {
        if (! in_array($consultation->status, [ConsultationStatus::InProgress, ConsultationStatus::Completed], true)) {
            throw ApiException::unprocessable('NOT_ACTIVE');
        }

        if ($consultation->patient === null) {
            throw ApiException::notFound(Consultation::NOT_FOUND);
        }

        $mdcn = DoctorProfile::query()
            ->where('user_id', $doctor->id)
            ->value('mdcn_number');

        $notes = trim((string) ($data['notes'] ?? ''));
        $tests = array_map(fn(array $test) => [
            'test_code' => trim($test['test_code']),
            'reason' => trim((string) ($test['reason'] ?? '')) ?: null,
        ], $data['tests']);

        return $this->requests->create($doctor, $consultation->patient, $consultation->uuid, (string) $mdcn, $tests, $notes === '' ? null : $notes);
    }

    public function of(Consultation $consultation): LabRequest
    {
        return $this->requests->forConsultation($consultation->uuid);
    }
}
