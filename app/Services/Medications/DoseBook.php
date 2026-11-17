<?php

namespace App\Services\Medications;

use App\Models\DoseLog;
use App\Models\Medication;
use App\Models\User;
use App\Support\LocalDay;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Reads doses. Every dose up to a schedule's `settled_through` is a `dose_logs` row; the doses after
 * it are derived from the schedule until they are answered or missed.
 */
class DoseBook
{
    /**
     * A patient's doses on one Lagos day, answered or not, in the order they are due.
     *
     * @return list<Dose>
     */
    public function day(User $patient, CarbonImmutable $day): array
    {
        $start = $day->startOfDay();
        $end = $start->addDay();

        $logs = DoseLog::query()
            ->with('medication')
            ->whereHas('medication')
            ->where('user_id', $patient->id)
            ->where('scheduled_for', '>=', $start->utc())
            ->where('scheduled_for', '<', $end->utc())
            ->get();

        $medications = Medication::query()
            ->where('user_id', $patient->id)
            ->whereNull('paused_at')
            ->where('start_date', '<=', $start->toDateString())
            ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $start->toDateString()))
            ->get();

        $answered = $logs->map(fn(DoseLog $log) => new Dose($log->medication, $log->scheduled_for, $log));
        $pending = $this->pending($medications, $start->subSecond(), $end->subSecond());

        return $answered
            ->concat($pending)
            ->sortBy(fn(Dose $dose) => [$dose->scheduledFor->getTimestamp(), $dose->medication->drug_name])
            ->values()
            ->all();
    }

    /**
     * The doses not yet answered or settled, after `$after` and up to `$until`.
     *
     * @param  Collection<int, Medication>  $medications
     * @return list<Dose>
     */
    public function pending(Collection $medications, CarbonImmutable $after, CarbonImmutable $until): array
    {
        $medications = $medications->filter(fn(Medication $m) => $m->paused_at === null && $m->settled_through->lessThan($until));

        if ($medications->isEmpty()) {
            return [];
        }

        $answered = DoseLog::query()
            ->join('medications', 'medications.id', '=', 'dose_logs.medication_id')
            ->whereIn('dose_logs.medication_id', $medications->modelKeys())
            ->whereColumn('dose_logs.scheduled_for', '>', 'medications.settled_through')
            ->where('dose_logs.scheduled_for', '>', $after->utc())
            ->where('dose_logs.scheduled_for', '<=', $until->utc())
            ->get(['dose_logs.medication_id', 'dose_logs.scheduled_for'])
            ->map(fn(DoseLog $log) => $log->medication_id . '@' . $log->scheduled_for->getTimestamp())
            ->flip();

        $doses = [];

        foreach ($medications as $medication) {
            $from = $after->max($medication->settled_through);

            foreach (DoseTimes::between($medication, $from, $until) as $instant) {
                if (! $answered->has($medication->id . '@' . $instant->getTimestamp())) {
                    $doses[] = new Dose($medication, $instant);
                }
            }
        }

        return $doses;
    }

    /**
     * The local day a dose falls on, as `YYYY-MM-DD`.
     */
    public static function dayOf(CarbonImmutable $instant): string
    {
        return LocalDay::containing($instant)->toDateString();
    }
}
