<?php

namespace App\Services\Consultations;

use App\Enums\ConsultationStatus;
use App\Enums\NotificationCategory;
use App\Exceptions\ApiException;
use App\Models\Consultation;
use App\Models\DoctorProfile;
use App\Models\Prescription;
use App\Models\User;
use App\Services\Medications\PrescribedSchedules;
use App\Services\Notifications\Notifier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Prescriptions (app spec §7.6, handover §1): the doctor issues one per consultation, checked against
 * the patient's recorded allergies; the patient reviews it and applies it to their schedule, once.
 */
class Prescribing
{
    public function __construct(
        private readonly AllergyCheck $allergies,
        private readonly PrescribedSchedules $schedules,
        private readonly Notifier $notifier,
    ) {}

    /**
     * A conflict with a recorded allergy is refused (422 `ALLERGY_CONFLICT`) unless the doctor has
     * seen it and says so. A profile the patient did not share is still checked, but the refusal
     * names only the drug and its class.
     *
     * @param  array{diagnosis_summary: string, drugs: list<array<string, mixed>>, acknowledge_allergy_conflicts?: bool}  $data
     */
    public function issue(Consultation $consultation, User $doctor, array $data): Prescription
    {
        if (! in_array($consultation->status, [ConsultationStatus::InProgress, ConsultationStatus::Completed], true)) {
            throw ApiException::unprocessable('NOT_ACTIVE');
        }

        $this->checkAllergies($consultation, $data);

        $prescription = DB::transaction(function () use ($consultation, $doctor, $data) {
            $existing = Prescription::query()
                ->where('consultation_id', $consultation->id)
                ->lockForUpdate()
                ->first();

            if ($existing?->applied_at !== null) {
                throw ApiException::conflict('PRESCRIPTION_APPLIED');
            }

            $existing?->delete();

            $mdcn = DoctorProfile::query()
                ->where('user_id', $doctor->id)
                ->value('mdcn_number');

            return Prescription::create([
                'consultation_id' => $consultation->id,
                'doctor_id' => $doctor->id,
                'doctor_name' => $consultation->doctor_name,
                'mdcn_number' => (string) $mdcn,
                'diagnosis_summary' => trim($data['diagnosis_summary']),
                'drugs' => $this->drugs($data['drugs']),
                'issued_at' => now(),
                'expires_at' => now()->addDays(config('elevenmed.consultations.prescription_valid_days')),
            ]);
        });

        $this->notifier->send(
            $consultation->patient,
            NotificationCategory::Prescription,
            __('consultations.prescribed.title', ['doctor' => $consultation->doctor_name]),
            __('consultations.prescribed.body'),
            [
                'target_id' => $consultation->uuid,
                'dedupe_key' => "prescription_issued:{$prescription->uuid}",
            ]
        );

        return $prescription;
    }

    public function of(Consultation $consultation): Prescription
    {
        $prescription = Prescription::query()
            ->where('consultation_id', $consultation->id)
            ->first();

        if ($prescription === null) {
            throw ApiException::notFound(Prescription::NOT_FOUND);
        }

        return $prescription;
    }

    /**
     * Adds the drugs to the patient's schedule, exempt from the free-tier cap. Applying again adds
     * nothing and answers the same medications.
     */
    public function apply(Consultation $consultation, User $patient): Collection
    {
        $prescription = $this->of($consultation);
        $doctor = $prescription->doctor;

        if ($doctor === null) {
            throw ApiException::unprocessable('PRESCRIBER_REMOVED');
        }

        $medications = $this->schedules->apply($patient, $doctor, $prescription->uuid, $prescription->diagnosis_summary, $prescription->drugs);

        if ($prescription->applied_at === null) {
            $prescription->applied_at = now();
            $prescription->save();
        }

        return $medications;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function checkAllergies(Consultation $consultation, array $data): void
    {
        if ((bool) ($data['acknowledge_allergy_conflicts'] ?? false)) {
            return;
        }

        $conflicts = $this->allergies->conflicts($consultation->patient, $data['drugs']);

        if ($conflicts === []) {
            return;
        }

        if (! $consultation->profile_shared) {
            $conflicts = array_map(fn(array $conflict) => [
                'drug_name' => $conflict['drug_name'],
                'drug_class' => $conflict['drug_class'],
            ], $conflicts);
        }

        throw ApiException::unprocessable('ALLERGY_CONFLICT', 'drugs', ['conflicts' => $conflicts]);
    }

    /**
     * @param  list<array<string, mixed>>  $drugs
     * @return list<array<string, mixed>>
     */
    private function drugs(array $drugs): array
    {
        return array_map(fn(array $drug) => [
            'drug_name' => trim($drug['drug_name']),
            'dosage_strength' => trim($drug['dosage_strength']),
            'dosage_form' => $drug['dosage_form'],
            'dose_quantity' => $drug['dose_quantity'] + 0,
            'dose_unit' => $drug['dose_unit'] ?? null,
            'times' => array_values($drug['times']),
            'duration_days' => (int) $drug['duration_days'],
            'special_instructions' => $drug['special_instructions'] ?? null,
        ], array_values($drugs));
    }
}
