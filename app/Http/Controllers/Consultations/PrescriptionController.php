<?php

namespace App\Http\Controllers\Consultations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consultations\IssuePrescriptionRequest;
use App\Http\Resources\Consultations\PrescriptionResource;
use App\Http\Resources\Medications\MedicationCollection;
use App\Services\Consultations\ConsultationRecords;
use App\Services\Consultations\Prescribing;
use Illuminate\Http\Request;

/**
 * Prescriptions (app spec §7.6): issued by the doctor, read and added to the schedule by the patient.
 */
class PrescriptionController extends Controller
{
    public function __construct(
        private readonly ConsultationRecords $records,
        private readonly Prescribing $prescribing,
    ) {}

    public function show(Request $request, string $id): PrescriptionResource
    {
        $prescription = $this->prescribing->of($this->records->ofPatient($request->user(), $id));

        return new PrescriptionResource($prescription);
    }

    public function issue(IssuePrescriptionRequest $request, string $id): PrescriptionResource
    {
        $prescription = $this->prescribing->issue($this->records->ofDoctor($request->user(), $id), $request->user(), $request->validated());

        return new PrescriptionResource($prescription);
    }

    /**
     * Idempotent: applying again returns the schedules the first time made.
     */
    public function apply(Request $request, string $id): MedicationCollection
    {
        $consultation = $this->records->ofPatient($request->user(), $id);
        $medications = $this->prescribing->apply($consultation, $request->user());

        $medications->load('prescriber');

        return new MedicationCollection($medications);
    }
}
