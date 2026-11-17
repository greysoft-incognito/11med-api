<?php

namespace App\Services\Medications;

use App\Models\DoseLog;
use App\Models\Medication;
use App\Models\User;
use App\Support\LocalDay;
use Carbon\CarbonImmutable;

/**
 * The compliance score (spec §6.4): doses taken, on time or late, over doses scheduled, per Lagos
 * day and across the window. A day with nothing scheduled scores null, not zero.
 */
class Compliance
{
    public function __construct(
        private readonly DoseBook $doses,
    ) {}

    /**
     * @return array{score: int, band: string, window_days: int, doses_taken: int, doses_scheduled: int, daily: list<array{date: string, score: ?int, taken: int, scheduled: int}>}
     */
    public function for(User $patient, CarbonImmutable $lastDay, int $windowDays): array
    {
        $start = $lastDay->subDays($windowDays - 1)->startOfDay();
        $end = $lastDay->addDay()->startOfDay();
        $days = $this->settledDays($patient, $start, $end);

        $medications = Medication::query()
            ->where('user_id', $patient->id)
            ->whereNull('paused_at')
            ->where('settled_through', '<', $end->utc())
            ->get();

        foreach ($this->doses->pending($medications, $start->subSecond(), $end->subSecond()) as $dose) {
            $day = DoseBook::dayOf($dose->scheduledFor);
            $days[$day]['scheduled'] = ($days[$day]['scheduled'] ?? 0) + 1;
            $days[$day]['taken'] ??= 0;
        }

        $daily = [];
        $taken = 0;
        $scheduled = 0;

        for ($day = $start; $day->lessThan($end); $day = $day->addDay()) {
            $date = $day->toDateString();
            $dayTaken = $days[$date]['taken'] ?? 0;
            $dayScheduled = $days[$date]['scheduled'] ?? 0;

            $taken += $dayTaken;
            $scheduled += $dayScheduled;
            $daily[] = [
                'date' => $date,
                'score' => $dayScheduled === 0 ? null : self::percent($dayTaken, $dayScheduled),
                'taken' => $dayTaken,
                'scheduled' => $dayScheduled,
            ];
        }

        $score = self::percent($taken, $scheduled);

        return [
            'score' => $score,
            'band' => self::band($score),
            'window_days' => $windowDays,
            'doses_taken' => $taken,
            'doses_scheduled' => $scheduled,
            'daily' => $daily,
        ];
    }

    /**
     * §6.4 thresholds, the client's `complianceBand`.
     */
    public static function band(int $score): string
    {
        return match (true) {
            $score >= 80 => 'good',
            $score >= 50 => 'moderate',
            default => 'poor',
        };
    }

    public static function percent(int $part, int $whole): int
    {
        return $whole === 0 ? 0 : (int) round($part / $whole * 100);
    }

    /**
     * Answered and missed doses per Lagos day, counted in SQL.
     *
     * @return array<string, array{taken: int, scheduled: int}>
     */
    private function settledDays(User $patient, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $zone = LocalDay::zone();

        return DoseLog::query()
            ->join('medications', 'medications.id', '=', 'dose_logs.medication_id')
            ->whereNull('medications.deleted_at')
            ->where('dose_logs.user_id', $patient->id)
            ->where('dose_logs.scheduled_for', '>=', $start->utc())
            ->where('dose_logs.scheduled_for', '<', $end->utc())
            ->selectRaw('(dose_logs.scheduled_for AT TIME ZONE ?)::date::text AS day', [$zone])
            ->selectRaw('COUNT(*) AS scheduled')
            ->selectRaw("COUNT(*) FILTER (WHERE dose_logs.state IN ('taken', 'taken_late')) AS taken")
            ->groupByRaw('1')
            ->toBase()
            ->get()
            ->mapWithKeys(fn(object $row) => [$row->day => ['taken' => (int) $row->taken, 'scheduled' => (int) $row->scheduled]])
            ->all();
    }
}
