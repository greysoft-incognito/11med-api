<?php

namespace App\Services\Medications;

use App\Enums\FrequencyType;
use App\Models\Medication;
use App\Models\User;
use App\Support\LocalDay;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns a prescription into schedules (app handover §1.2, §1.3), for the Consultations module.
 * Exempt from the free-tier cap, and idempotent: applying the same prescription again returns
 * the schedules it made the first time, even ones the patient has since deleted.
 */
class Prescriptions implements PrescribedSchedules
{
    public function __construct(
        private readonly Schedules $schedules,
    ) {}

    /**
     * @param  list<array{drug_name: string, dosage_strength: string, dosage_form: string, dose_quantity: float|int, dose_unit: ?string, times: list<string>, duration_days: int, special_instructions: ?string}>  $drugs  `zPrescribedDrug`
     * @return Collection<int, Medication>
     */
    public function apply(User $patient, User $doctor, string $prescriptionId, string $diagnosis, array $drugs): Collection
    {
        return DB::transaction(function () use ($patient, $prescriptionId, $doctor, $diagnosis, $drugs) {
            $patient
                ->newQuery()
                ->lockForUpdate()
                ->find($patient->id);

            $applied = Medication::withTrashed()
                ->where('user_id', $patient->id)
                ->where('prescription_id', $prescriptionId)
                ->orderBy('id')
                ->get();

            if ($applied->isNotEmpty()) {
                return $applied;
            }

            $today = LocalDay::today();
            $created = new Collection;

            foreach ($drugs as $drug) {
                $created->push($this->schedule($patient, $prescriptionId, $doctor, $diagnosis, $today, $drug));
            }

            return $created;
        });
    }

    /**
     * @param  array<string, mixed>  $drug
     */
    private function schedule(User $patient, string $prescriptionId, User $doctor, string $diagnosis, CarbonImmutable $today, array $drug): Medication
    {
        $times = array_values(array_unique($drug['times']));
        sort($times);

        $medication = new Medication([
            'user_id' => $patient->id,
            'drug_name' => $drug['drug_name'],
            'dosage_strength' => $drug['dosage_strength'],
            'dosage_form' => $drug['dosage_form'],
            'dose_quantity' => $drug['dose_quantity'],
            'dose_unit' => $drug['dose_unit'] ?? null,
            'frequency_type' => self::frequency(count($times)),
            'specific_times' => $times,
            'start_date' => $today->toDateString(),
            'end_date' => $today->addDays($drug['duration_days'] - 1)->toDateString(),
            'with_food' => 'not_specified',
            'special_instructions' => $drug['special_instructions'] ?? null,
            'condition_being_treated' => mb_substr($diagnosis, 0, 160),
            'reminder_active' => true,
            'caregiver_alerts' => false,
            'prescribed_by_doctor_id' => $doctor->id,
            'prescription_id' => $prescriptionId,
        ]);

        $medication->settled_through = $this->schedules->settledFrom($medication);
        $medication->save();

        return $medication;
    }

    private static function frequency(int $times): FrequencyType
    {
        return match ($times) {
            1 => FrequencyType::Daily,
            2 => FrequencyType::TwiceDaily,
            3 => FrequencyType::ThreeTimesDaily,
            4 => FrequencyType::FourTimesDaily,
            default => FrequencyType::Custom,
        };
    }
}
