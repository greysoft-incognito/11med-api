<?php

namespace App\Services\Medications;

use App\Enums\DoseState;
use App\Events\Medications\DoseMissed;
use App\Models\DoseLog;
use App\Models\Medication;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The sixty-minute rule (spec §6.3.1), on the scheduler: every dose past its deadline without an
 * answer, or only snoozed, becomes `missed`, once. The patient is told about the recent ones.
 */
class MissedDoses
{
    private const BATCH = 200;

    /**
     * Settles every schedule up to `$now` less the deadline; returns how many doses were missed.
     */
    public function settle(CarbonImmutable $now): int
    {
        $cutoff = $now->subMinutes(config('elevenmed.medications.missed_after_minutes'));
        $noticeFrom = $now->subDays(config('elevenmed.medications.missed_notice_days'));
        $missed = $this->settleSnoozed($cutoff, $noticeFrom);

        Medication::query()
            ->with('user')
            ->whereNull('paused_at')
            ->where('settled_through', '<', $cutoff)
            ->where(fn(Builder $q) => $q
                ->whereNull('end_date')
                ->orWhereRaw('end_date >= (settled_through AT TIME ZONE ?)::date', [config('elevenmed.timezone')]))
            ->chunkById(self::BATCH, function (Collection $medications) use ($cutoff, $noticeFrom, &$missed) {
                foreach ($medications as $medication) {
                    $missed += $this->settleSchedule($medication, $cutoff, $noticeFrom);
                }
            });

        return $missed;
    }

    /**
     * A snooze defers the reminder, never the deadline.
     */
    private function settleSnoozed(CarbonImmutable $cutoff, CarbonImmutable $noticeFrom): int
    {
        $missed = 0;

        DoseLog::query()
            ->with('medication.user')
            ->where('state', DoseState::Snoozed)
            ->where('scheduled_for', '<=', $cutoff)
            ->chunkById(self::BATCH, function (Collection $logs) use ($noticeFrom, &$missed) {
                foreach ($logs as $log) {
                    $changed = DoseLog::query()
                        ->whereKey($log->id)
                        ->where('state', DoseState::Snoozed)
                        ->update(['state' => DoseState::Missed, 'updated_at' => now()]);

                    if ($changed === 1 && $log->medication !== null && $log->scheduled_for->greaterThanOrEqualTo($noticeFrom)) {
                        $this->announce($log->medication, $log->scheduled_for);
                    }

                    $missed += $changed;
                }
            });

        return $missed;
    }

    private function settleSchedule(Medication $medication, CarbonImmutable $cutoff, CarbonImmutable $noticeFrom): int
    {
        $missing = DB::transaction(function () use ($medication, $cutoff) {
            $locked = Medication::query()
                ->whereKey($medication->id)
                ->whereNull('paused_at')
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->settled_through->greaterThanOrEqualTo($cutoff)) {
                return [];
            }

            $answered = DoseLog::query()
                ->where('medication_id', $locked->id)
                ->where('scheduled_for', '>', $locked->settled_through)
                ->where('scheduled_for', '<=', $cutoff)
                ->pluck('scheduled_for')
                ->map(fn(CarbonImmutable $at) => $at->getTimestamp())
                ->flip();

            $missing = array_values(array_filter(
                DoseTimes::between($locked, $locked->settled_through, $cutoff),
                fn(CarbonImmutable $at) => ! $answered->has($at->getTimestamp())
            ));

            $rows = array_map(fn(CarbonImmutable $at) => [
                'uuid' => (string) str()->uuid7(),
                'user_id' => $locked->user_id,
                'medication_id' => $locked->id,
                'scheduled_for' => $at,
                'state' => DoseState::Missed->value,
                'snooze_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ], $missing);

            foreach (array_chunk($rows, self::BATCH) as $chunk) {
                DoseLog::query()->insert($chunk);
            }

            $locked->settled_through = $cutoff;
            $locked->save();

            return $missing;
        });

        foreach ($missing as $at) {
            if ($at->greaterThanOrEqualTo($noticeFrom)) {
                $this->announce($medication, $at);
            }
        }

        return count($missing);
    }

    private function announce(Medication $medication, CarbonImmutable $scheduledFor): void
    {
        DoseMissed::dispatch(
            $medication->user,
            $medication->uuid,
            $medication->drug_name,
            $medication->dosage_strength,
            $scheduledFor,
        );
    }
}
