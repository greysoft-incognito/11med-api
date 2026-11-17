<?php

namespace App\Http\Resources\Medications;

use App\Http\Resources\ApiResource;
use App\Models\Medication;
use App\Support\LocalDay;
use Illuminate\Http\Request;

/**
 * `zMedication`.
 *
 * @property Medication $resource
 */
class MedicationResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $medication = $this->resource;
        $end = $medication->end_date;

        return [
            'id' => $medication->uuid,
            'drug_name' => $medication->drug_name,
            'brand_name' => $medication->brand_name,
            'dosage_strength' => $medication->dosage_strength,
            'dosage_form' => $medication->dosage_form->value,
            'dose_quantity' => $medication->dose_quantity,
            'dose_unit' => $medication->dose_unit?->value,
            'frequency_type' => $medication->frequency_type->value,
            'specific_times' => $medication->specific_times,
            'start_date' => $this->day($medication->start_date),
            'end_date' => $this->day($end),
            'total_duration_days' => $end === null ? null : (int) $medication->start_date->diffInDays($end) + 1,
            'with_food' => $medication->with_food->value,
            'special_instructions' => $medication->special_instructions,
            'condition_being_treated' => $medication->condition_being_treated,
            'reminder_active' => $medication->reminder_active,
            'caregiver_alerts' => $medication->caregiver_alerts,
            'refill_reminder_days' => $medication->refill_reminder_days,
            'estimated_supply_units' => $medication->estimated_supply_units,
            'paused_at' => $this->iso($medication->paused_at),
            'is_active' => $medication->isActive(LocalDay::today()),
            'prescribed_by_doctor_id' => $medication->prescriber?->uuid,
            'prescription_id' => $medication->prescription_id,
            'created_at' => $this->iso($medication->created_at),
        ];
    }
}
