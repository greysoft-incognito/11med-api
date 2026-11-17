<?php

namespace App\Services\Consultations;

use App\Models\User;
use App\Services\Pharmacy\PrescribedDrugs;
use Illuminate\Support\Facades\DB;

/**
 * The medicines a patient holds an unexpired 11med prescription for, by name, lower case, for the
 * pharmacy's prescription-required check (app mock `prescribedDrugNames`). One query.
 */
class ConsultationPrescribedDrugs implements PrescribedDrugs
{
    public function names(User $patient): array
    {
        return DB::table('prescriptions')
            ->join('consultations', 'consultations.id', '=', 'prescriptions.consultation_id')
            ->crossJoin(DB::raw('jsonb_array_elements(prescriptions.drugs) as drug'))
            ->where('consultations.patient_id', $patient->id)
            ->whereNull('prescriptions.deleted_at')
            ->where('prescriptions.expires_at', '>', now())
            ->selectRaw("distinct lower(trim(drug->>'drug_name')) as name")
            ->pluck('name')
            ->all();
    }
}
