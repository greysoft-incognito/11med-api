<?php

namespace App\Services\Consultations;

use App\Models\Consultation;
use App\Models\ConsultationMessage;
use App\Models\Prescription;
use App\Models\User;
use App\Services\Identity\Export\PersonalData;
use App\Services\Identity\Export\PersonalDataWriter;

/**
 * Consultations the account took part in, as patient or doctor, with their chat and prescriptions.
 */
class ConsultationPersonalData implements PersonalData
{
    public function export(User $user, PersonalDataWriter $writer): void
    {
        $consultations = Consultation::query()->where(fn($query) => $query->where('patient_id', $user->id)->orWhere('doctor_id', $user->id));

        $writer->section('consultations', 'Consultations', $consultations->clone());
        $writer->section('consultation_messages', 'Consultation chat', ConsultationMessage::whereIn('consultation_id', $consultations->clone()->select('id')));
        $writer->section('prescriptions', 'Prescriptions', Prescription::whereIn('consultation_id', $consultations->clone()->select('id')));
    }
}
