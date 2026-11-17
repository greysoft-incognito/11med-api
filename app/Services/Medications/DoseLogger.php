<?php

namespace App\Services\Medications;

use App\Enums\DoseState;
use App\Exceptions\ApiException;
use App\Models\DoseLog;
use App\Models\Medication;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The patient's answers to doses (spec §6.3.2). A dose may be answered and re-answered; a snooze
 * is allowed twice and never moves the sixty-minute deadline.
 */
class DoseLogger
{
    /**
     * @param  array{dose_id: string, state: string, skip_reason?: ?string, logged_at: string}  $entry
     */
    public function log(User $patient, array $entry): void
    {
        $this->apply($patient, $entry, clampSnooze: false);
    }

    /**
     * Drains the offline queue in the order the answers were given. These were committed on the
     * phone without the server's count, so a spent snooze is clamped rather than refused, and an
     * answer to a schedule deleted since is dropped instead of stranding the batch.
     *
     * @param  list<array{dose_id: string, state: string, skip_reason?: ?string, logged_at: string}>  $entries
     */
    public function logBatch(User $patient, array $entries): void
    {
        usort($entries, fn(array $a, array $b) => strcmp(
            CarbonImmutable::parse($a['logged_at'])->utc()->toIso8601ZuluString('microsecond'),
            CarbonImmutable::parse($b['logged_at'])->utc()->toIso8601ZuluString('microsecond'),
        ));

        foreach ($entries as $entry) {
            try {
                $this->apply($patient, $entry, clampSnooze: true);
            } catch (ApiException $refused) {
                if ($refused->errorCode !== DoseLog::NOT_FOUND) {
                    throw $refused;
                }
            }
        }
    }

    /**
     * Takes back an answer. A dose still inside its hour returns to unanswered; one past it is
     * missed, which is what an unanswered dose that late is. The retracted row is kept, deleted.
     */
    public function retract(User $patient, string $doseId): void
    {
        $dose = DoseId::parse($doseId) ?? throw ApiException::notFound('DOSE_NOT_ANSWERED');

        DB::transaction(function () use ($patient, $dose) {
            $medication = $this->lockMedication($patient, $dose, 'DOSE_NOT_ANSWERED');
            $log = $this->liveLog($medication, $dose->scheduledFor);

            if ($log === null || $log->state === DoseState::Missed && $log->logged_at === null) {
                throw ApiException::notFound('DOSE_NOT_ANSWERED');
            }

            $log->delete();

            if ($dose->scheduledFor->lessThanOrEqualTo($medication->settled_through)) {
                DoseLog::create([
                    'user_id' => $patient->id,
                    'medication_id' => $medication->id,
                    'scheduled_for' => $dose->scheduledFor,
                    'state' => DoseState::Missed,
                    'snooze_count' => $log->snooze_count,
                ]);
            }
        });
    }

    /**
     * @param  array{dose_id: string, state: string, skip_reason?: ?string, logged_at: string}  $entry
     */
    private function apply(User $patient, array $entry, bool $clampSnooze): void
    {
        $dose = DoseId::parse($entry['dose_id']) ?? throw ApiException::notFound(DoseLog::NOT_FOUND, 'dose_id');
        $state = DoseState::from($entry['state']);
        $loggedAt = CarbonImmutable::parse($entry['logged_at'])->utc();

        if ($loggedAt->greaterThan(now()->addMinutes(config('elevenmed.medications.future_tolerance_minutes')))) {
            throw ApiException::badRequest('VALIDATION_FAILED', 'logged_at');
        }

        DB::transaction(function () use ($patient, $dose, $state, $loggedAt, $entry, $clampSnooze) {
            $medication = $this->lockMedication($patient, $dose, DoseLog::NOT_FOUND);
            $log = $this->liveLog($medication, $dose->scheduledFor);

            if (! DoseTimes::isDoseOf($medication, $dose->scheduledFor)) {
                throw ApiException::notFound(DoseLog::NOT_FOUND, 'dose_id');
            }

            // Before `settled_through` every dose has a row; an instant without one was never owed.
            if ($log === null && $dose->scheduledFor->lessThanOrEqualTo($medication->settled_through)) {
                throw ApiException::notFound(DoseLog::NOT_FOUND, 'dose_id');
            }

            $snoozes = $log->snooze_count ?? 0;

            if ($state === DoseState::Snoozed) {
                $max = config('elevenmed.medications.max_snoozes');

                if ($snoozes >= $max && ! $clampSnooze) {
                    throw ApiException::unprocessable('SNOOZE_LIMIT_REACHED', 'state');
                }

                $snoozes = min($snoozes + 1, $max);
            }

            $log ??= new DoseLog([
                'user_id' => $patient->id,
                'medication_id' => $medication->id,
                'scheduled_for' => $dose->scheduledFor,
            ]);

            $log->fill([
                'state' => $state,
                'skip_reason' => $state === DoseState::Skipped ? ($entry['skip_reason'] ?? null) : null,
                'logged_at' => $loggedAt,
                'snooze_count' => $snoozes,
            ]);
            $log->save();
        });
    }

    private function lockMedication(User $patient, DoseId $dose, string $notFound): Medication
    {
        return Medication::query()
            ->where('user_id', $patient->id)
            ->where('uuid', $dose->medicationId)
            ->lockForUpdate()
            ->first() ?? throw ApiException::notFound($notFound, $notFound === DoseLog::NOT_FOUND ? 'dose_id' : null);
    }

    private function liveLog(Medication $medication, CarbonImmutable $scheduledFor): ?DoseLog
    {
        return DoseLog::query()
            ->where('medication_id', $medication->id)
            ->where('scheduled_for', $scheduledFor)
            ->lockForUpdate()
            ->first();
    }
}
