<?php

namespace App\Http\Resources\Medications;

use App\Http\Resources\ApiResource;
use App\Services\Medications\Dose;
use Illuminate\Http\Request;

/**
 * `zDose`: a schedule's dose and its answer, if any.
 *
 * @property Dose $resource
 */
class DoseResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $dose = $this->resource;
        $medication = $dose->medication;
        $log = $dose->log;

        return [
            'id' => $dose->id(),
            'medication_id' => $medication->uuid,
            'medication_name' => $medication->drug_name,
            'dosage_strength' => $medication->dosage_strength,
            'dose_quantity' => $medication->dose_quantity,
            'dose_unit' => $medication->dose_unit?->value,
            'dosage_form' => $medication->dosage_form->value,
            'with_food' => $medication->with_food->value,
            'scheduled_for' => $this->iso($dose->scheduledFor),
            'logged_at' => $this->iso($log?->logged_at),
            'state' => $log?->state->value,
            'skip_reason' => $log?->skip_reason?->value,
            'snooze_count' => $log->snooze_count ?? 0,
        ];
    }
}
