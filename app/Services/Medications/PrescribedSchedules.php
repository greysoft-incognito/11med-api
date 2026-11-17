<?php

namespace App\Services\Medications;

use App\Models\Medication;
use App\Models\User;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Database\Eloquent\Collection;

/**
 * Turns a prescription into the patient's schedules (app handover §1.2 and §1.3). The Medications
 * module (M6) implements it; Consultations (M10) calls it from the apply route.
 */
#[Bind(NoPrescribedSchedules::class)]
interface PrescribedSchedules
{
    /**
     * Adds each drug as a schedule exempt from the free-tier cap, with the diagnosis as the
     * condition being treated. Idempotent on the patient and prescription, trashed rows included:
     * applying again adds nothing and returns the same medications.
     *
     * @param  list<array<string, mixed>>  $drugs  `zPrescribedDrug` items as the doctor issued them
     * @return Collection<int, Medication>
     */
    public function apply(User $patient, User $doctor, string $prescriptionId, string $diagnosis, array $drugs): Collection;
}
