<?php

namespace App\Services\Medications;

use App\Models\User;
use Illuminate\Container\Attributes\Bind;

/**
 * Turns a prescription into the patient's schedules (app handover §1.2 and §1.3). The Medications
 * module (M6) implements it; Consultations (M10) calls it from the apply route.
 */
#[Bind(NoPrescribedSchedules::class)]
interface PrescribedSchedules
{
    /**
     * Adds each drug as a schedule exempt from the free-tier cap. Idempotent: applying the same
     * prescription again adds nothing and returns the same medications.
     *
     * @param  list<array<string, mixed>>  $drugs  `zPrescribedDrug` items as the doctor issued them
     * @return list<string> the public ids of the patient's medications from this prescription
     */
    public function apply(User $patient, User $doctor, string $prescriptionId, array $drugs): array;
}
