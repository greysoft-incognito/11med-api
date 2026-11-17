<?php

namespace App\Services\Consultations;

use App\Enums\ConsultationStatus;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\Consultation;
use App\Models\ConsultationMessage;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reading consultations as their patient or their doctor. Anyone else's is 404, and a doctor never
 * sees a request before it is paid (app handover §16).
 */
class ConsultationRecords
{
    public function forPatient(User $patient, ListQuery $list): Page
    {
        $query = Consultation::query()
            ->where('patient_id', $patient->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return $list->paginate($query);
    }

    public function ofPatient(User $patient, string $id): Consultation
    {
        $consultation = Consultation::query()
            ->where('patient_id', $patient->id)
            ->where('uuid', $id)
            ->first();

        if ($consultation === null) {
            throw ApiException::notFound(Consultation::NOT_FOUND);
        }

        return $consultation;
    }

    /**
     * The doctor's inbox: what waits on them first, then the newest.
     */
    public function inbox(User $doctor, ListQuery $list): Page
    {
        $query = $this
            ->doctorQuery($doctor)
            ->with(['patient.medicalProfile.user', 'patient.medicalProfile.photo', 'followUpOf:id,complaint'])
            ->orderBy('inbox_rank')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return $list->paginate($query);
    }

    public function ofDoctor(User $doctor, string $id): Consultation
    {
        $consultation = $this
            ->doctorQuery($doctor)
            ->where('uuid', $id)
            ->first();

        if ($consultation === null) {
            throw ApiException::notFound(Consultation::NOT_FOUND);
        }

        return $consultation;
    }

    /**
     * An erased patient's consultations (app handover §3.1). A completed one stays for the doctor's
     * earnings, without the patient, the complaint, the chat or the prescription; the rest go.
     */
    public function forget(User $patient): void
    {
        $mine = Consultation::withTrashed()->where('patient_id', $patient->id);

        $mine
            ->clone()
            ->select('id')
            ->chunkById(200, function ($chunk) {
                $ids = $chunk->modelKeys();

                ConsultationMessage::withTrashed()
                    ->whereIn('consultation_id', $ids)
                    ->forceDelete();

                Prescription::withTrashed()
                    ->whereIn('consultation_id', $ids)
                    ->forceDelete();
            });

        $mine
            ->clone()
            ->where('status', ConsultationStatus::Completed)
            ->update([
                'patient_id' => null,
                'complaint' => '',
                'review' => null,
            ]);

        $mine
            ->clone()
            ->forceDelete();
    }

    private function doctorQuery(User $doctor): Builder
    {
        return Consultation::query()
            ->where('doctor_id', $doctor->id)
            ->whereNotNull('paid_at');
    }
}
