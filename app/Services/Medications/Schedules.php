<?php

namespace App\Services\Medications;

use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\Medication;
use App\Models\User;
use App\Services\Caregiving\Caregivers;
use App\Services\Subscriptions\Entitlements;
use App\Support\LocalDay;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Medication schedules (app spec §6.2): the patient's to write, readable by an accepted caregiver.
 * The free-tier cap counts active schedules the patient added; a prescribed one never counts.
 */
class Schedules
{
    private const TIMING = ['specific_times', 'start_date', 'end_date'];

    public function __construct(
        private readonly Entitlements $entitlements,
        private readonly Caregivers $caregivers,
    ) {}

    /**
     * The patient whose schedule `$caller` asks for: themselves, or someone they care for.
     */
    public function readable(User $caller, string $userId): User
    {
        if ($caller->uuid === $userId) {
            return $caller;
        }

        $patient = User::query()
            ->where('uuid', $userId)
            ->first();

        if ($patient === null || ! $this->caregivers->canRead($caller, $patient)) {
            throw ApiException::notFound();
        }

        return $patient;
    }

    public function of(User $patient, ListQuery $list): Page
    {
        $query = Medication::query()
            ->with('prescriber:id,uuid')
            ->where('user_id', $patient->id)
            ->orderBy('created_at')
            ->orderBy('id');

        return $list->paginate($query);
    }

    public function find(User $patient, string $medicationId): Medication
    {
        return Medication::query()
            ->where('user_id', $patient->id)
            ->where('uuid', $medicationId)
            ->first() ?? throw ApiException::notFound(Medication::NOT_FOUND);
    }

    /**
     * @param  array<string, mixed>  $data  `zCreateMedicationRequest`, validated
     */
    public function create(User $patient, array $data): Medication
    {
        return DB::transaction(function () use ($patient, $data) {
            $this->lockPatient($patient);
            $this->checkCap($patient);

            $medication = new Medication([
                'user_id' => $patient->id,
                'with_food' => 'not_specified',
                'caregiver_alerts' => false,
            ]);

            $medication->fill($this->normalise($data));
            $medication->settled_through = $this->settledFrom($medication);
            $medication->save();

            return $medication;
        });
    }

    /**
     * @param  array<string, mixed>  $data  `zUpdateMedicationRequest`, validated: absent keys are left alone
     */
    public function update(User $patient, string $medicationId, array $data): Medication
    {
        return DB::transaction(function () use ($patient, $medicationId, $data) {
            $this->lockPatient($patient);

            $medication = $this->find($patient, $medicationId);
            $today = LocalDay::today();
            $wasActive = $medication->isActive($today);

            $medication->fill($this->normalise($data));

            if ($medication->end_date !== null && $medication->end_date->lessThan($medication->start_date)) {
                throw ApiException::badRequest('VALIDATION_FAILED', 'end_date');
            }

            if (! $wasActive && $medication->isActive($today) && $medication->prescribed_by_doctor_id === null) {
                $this->checkCap($patient);
            }

            // A new timing applies from now: the doses already settled keep the times they had.
            if ($medication->isDirty(self::TIMING)) {
                $medication->settled_through = $medication->settled_through->max($this->settledFrom($medication));
            }

            $medication->save();

            return $medication;
        });
    }

    /**
     * A pause stops new doses; pausing again keeps the moment it began (spec item 9).
     */
    public function pause(User $patient, string $medicationId): Medication
    {
        $medication = $this->find($patient, $medicationId);

        if ($medication->paused_at === null) {
            $medication->paused_at = now();
            $medication->save();
        }

        return $medication;
    }

    /**
     * Resuming takes a place back under the cap, and the paused days have no doses.
     */
    public function resume(User $patient, string $medicationId): Medication
    {
        return DB::transaction(function () use ($patient, $medicationId) {
            $this->lockPatient($patient);

            $medication = $this->find($patient, $medicationId);

            if ($medication->paused_at === null) {
                return $medication;
            }

            $medication->paused_at = null;

            if ($medication->prescribed_by_doctor_id === null && $medication->isActive(LocalDay::today())) {
                $this->checkCap($patient);
            }

            $medication->settled_through = $medication->settled_through->max(CarbonImmutable::now());
            $medication->save();

            return $medication;
        });
    }

    public function delete(User $patient, string $medicationId): void
    {
        $this->find($patient, $medicationId)->delete();
    }

    /**
     * Refuses a new active schedule over the free-tier cap (422 `MEDICATION_LIMIT_REACHED`).
     */
    public function checkCap(User $patient): void
    {
        $limit = $this->entitlements->medicationLimit($patient);

        if ($limit === null) {
            return;
        }

        $active = Medication::query()
            ->where('user_id', $patient->id)
            ->whereNull('prescribed_by_doctor_id')
            ->active(LocalDay::today())
            ->count();

        if ($active >= $limit) {
            throw ApiException::unprocessable('MEDICATION_LIMIT_REACHED', null, ['limit' => $limit, 'active' => $active]);
        }
    }

    /**
     * Where a new or retimed schedule's doses begin: the last hour's doses stay answerable, and
     * nothing before the course starts or before now less that hour is owed.
     */
    public function settledFrom(Medication $medication): CarbonImmutable
    {
        $answerable = CarbonImmutable::now()->subMinutes(config('elevenmed.medications.missed_after_minutes'));
        $courseStart = LocalDay::of($medication->start_date->toDateString())->subSecond()->utc();

        return $answerable->max($courseStart);
    }

    private function lockPatient(User $patient): void
    {
        $patient
            ->newQuery()
            ->lockForUpdate()
            ->find($patient->id);
    }

    /**
     * Omitted and null optional fields both mean absent (app `zCreateMedicationRequest`).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalise(array $data): array
    {
        if (array_key_exists('with_food', $data) && $data['with_food'] === null) {
            $data['with_food'] = 'not_specified';
        }

        if (array_key_exists('caregiver_alerts', $data) && $data['caregiver_alerts'] === null) {
            $data['caregiver_alerts'] = false;
        }

        if (isset($data['specific_times'])) {
            $times = array_values(array_unique($data['specific_times']));
            sort($times);
            $data['specific_times'] = $times;
        }

        return Arr::except($data, ['user_id', 'paused_at', 'settled_through', 'prescribed_by_doctor_id', 'prescription_id']);
    }
}
