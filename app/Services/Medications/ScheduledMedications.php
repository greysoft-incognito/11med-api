<?php

namespace App\Services\Medications;

use App\Models\Medication;
use App\Models\User;
use App\Services\Profile\CurrentMedications;
use App\Support\LocalDay;

/**
 * What a patient takes now, for the emergency page: the active schedules, by name.
 */
class ScheduledMedications implements CurrentMedications
{
    private const SHOWN = 30;

    public function forEmergency(User $patient): array
    {
        return Medication::query()
            ->where('user_id', $patient->id)
            ->active(LocalDay::today())
            ->orderBy('drug_name')
            ->limit(self::SHOWN)
            ->get()
            ->map(fn(Medication $m) => [
                'name' => $m->drug_name,
                'strength' => $m->dosage_strength,
                'times' => $m->specific_times,
            ])
            ->all();
    }
}
