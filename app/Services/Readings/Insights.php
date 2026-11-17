<?php

namespace App\Services\Readings;

use App\Enums\DaySlot;
use App\Models\DoseLog;
use App\Models\Reading;
use App\Models\User;
use App\Services\Medications\Compliance;
use App\Services\Subscriptions\Entitlements;
use App\Support\LocalDay;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Health insights (app handover §14): formulas over the last thirty days, sent as their inputs and
 * only when there is enough behind them. Premium. Every figure is aggregated in SQL.
 */
class Insights
{
    public const WINDOW_DAYS = 30;

    private const RECENT_DAYS = 7;

    private const MIN_DOSES = 10;

    private const MIN_GROUP_DOSES = 5;

    private const MIN_GAP_POINTS = 10;

    private const MIN_READINGS_PER_SIDE = 3;

    private const SLOT_SQL = "CASE WHEN h BETWEEN 5 AND 11 THEN 'morning' WHEN h BETWEEN 12 AND 16 THEN 'afternoon' WHEN h BETWEEN 17 AND 20 THEN 'evening' ELSE 'night' END";

    public function __construct(
        private readonly Entitlements $entitlements,
    ) {}

    /**
     * @return array{window_days: int, items: list<array<string, mixed>>}
     */
    public function for(User $patient): array
    {
        $this->entitlements->requirePremium($patient);

        $now = CarbonImmutable::now();

        return [
            'window_days' => self::WINDOW_DAYS,
            'items' => [...$this->doseInsights($patient, $now), ...$this->readingTrends($patient, $now)],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function doseInsights(User $patient, CarbonImmutable $now): array
    {
        $settled = DoseLog::query()
            ->join('medications', 'medications.id', '=', 'dose_logs.medication_id')
            ->whereNull('medications.deleted_at')
            ->where('dose_logs.user_id', $patient->id)
            ->whereIn('dose_logs.state', ['taken', 'taken_late', 'missed', 'skipped'])
            ->where('dose_logs.scheduled_for', '>', $now->subDays(self::WINDOW_DAYS))
            ->where('dose_logs.scheduled_for', '<=', $now)
            ->selectRaw('dose_logs.medication_id, medications.drug_name')
            ->selectRaw('EXTRACT(HOUR FROM dose_logs.scheduled_for AT TIME ZONE ?)::int AS h', [LocalDay::zone()])
            ->selectRaw("(dose_logs.state IN ('taken', 'taken_late'))::int AS taken")
            ->toBase();

        $slots = DB::query()
            ->fromSub($settled, 's')
            ->selectRaw(self::SLOT_SQL . ' AS slot, COUNT(*) AS total, COUNT(*) - SUM(taken) AS missed')
            ->groupByRaw('1')
            ->get();

        $total = (int) $slots->sum('total');

        if ($total < self::MIN_DOSES) {
            return [];
        }

        $items = [];
        $missedAll = (int) $slots->sum('missed');
        $worst = $slots
            ->filter(fn(object $s) => $s->total >= self::MIN_GROUP_DOSES)
            ->sortByDesc(fn(object $s) => Compliance::percent((int) $s->missed, (int) $s->total))
            ->first();

        if ($worst !== null && $total > $worst->total) {
            $missedPercent = Compliance::percent((int) $worst->missed, (int) $worst->total);
            $otherPercent = Compliance::percent($missedAll - (int) $worst->missed, $total - (int) $worst->total);

            if ($missedPercent >= $otherPercent + self::MIN_GAP_POINTS) {
                $items[] = [
                    'kind' => 'missed_by_time_of_day',
                    'slot' => DaySlot::from($worst->slot)->value,
                    'missed_percent' => $missedPercent,
                    'other_percent' => $otherPercent,
                    'window_days' => self::WINDOW_DAYS,
                ];
            }
        }

        $medications = DB::query()
            ->fromSub($settled, 's')
            ->selectRaw('medication_id, MIN(drug_name) AS name, COUNT(*) AS total, SUM(taken) AS taken')
            ->groupBy('medication_id')
            ->get();

        $overall = Compliance::percent($total - $missedAll, $total);
        $weakest = $medications
            ->filter(fn(object $m) => $m->total >= self::MIN_GROUP_DOSES)
            ->sortBy(fn(object $m) => Compliance::percent((int) $m->taken, (int) $m->total))
            ->first();

        if ($weakest !== null && $medications->count() > 1) {
            $score = Compliance::percent((int) $weakest->taken, (int) $weakest->total);

            if ($score <= $overall - self::MIN_GAP_POINTS) {
                $items[] = [
                    'kind' => 'weakest_medication',
                    'medication_name' => $weakest->name,
                    'score' => $score,
                    'overall_score' => $overall,
                    'window_days' => self::WINDOW_DAYS,
                ];
            }
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readingTrends(User $patient, CarbonImmutable $now): array
    {
        $split = $now->subDays(self::RECENT_DAYS);

        $sides = Reading::query()
            ->where('user_id', $patient->id)
            ->where('taken_at', '>=', $now->subDays(self::WINDOW_DAYS))
            ->selectRaw('type, (taken_at >= ?) AS recent, COUNT(*) AS n', [$split])
            ->selectRaw('AVG(COALESCE(systolic, mmol_l, kg)) AS value, AVG(diastolic) AS secondary')
            ->groupByRaw('1, 2')
            ->toBase()
            ->get()
            ->groupBy('type');

        $items = [];

        foreach (['blood_pressure', 'blood_glucose', 'weight'] as $type) {
            $recent = $sides->get($type)?->firstWhere('recent', true);
            $earlier = $sides->get($type)?->firstWhere('recent', false);

            if ($recent === null || $earlier === null || $recent->n < self::MIN_READINGS_PER_SIDE || $earlier->n < self::MIN_READINGS_PER_SIDE) {
                continue;
            }

            $places = $type === 'blood_glucose' ? 1 : 0;
            $recentValue = round((float) $recent->value, $places);
            $earlierValue = round((float) $earlier->value, $places);
            $recentSecondary = $recent->secondary === null ? null : round((float) $recent->secondary);
            $earlierSecondary = $earlier->secondary === null ? null : round((float) $earlier->secondary);

            $items[] = [
                'kind' => 'reading_trend',
                'reading_type' => $type,
                'direction' => self::direction($type, $recentValue - $earlierValue, $recentSecondary, $earlierSecondary),
                'recent_value' => $places === 0 ? (int) $recentValue : $recentValue,
                'earlier_value' => $places === 0 ? (int) $earlierValue : $earlierValue,
                'recent_secondary' => $recentSecondary === null ? null : (int) $recentSecondary,
                'earlier_secondary' => $earlierSecondary === null ? null : (int) $earlierSecondary,
                'window_days' => self::WINDOW_DAYS,
            ];
        }

        return $items;
    }

    /**
     * Up or down when the means differ by 5 systolic or 3 diastolic, 0.5 mmol/L, or 1 kg.
     */
    private static function direction(string $type, float $diff, ?float $recentSecondary, ?float $earlierSecondary): string
    {
        $step = match ($type) {
            'blood_pressure' => 5,
            'blood_glucose' => 0.5,
            default => 1,
        };
        $secondaryDiff = $recentSecondary !== null && $earlierSecondary !== null ? $recentSecondary - $earlierSecondary : 0;

        return match (true) {
            $diff >= $step || $secondaryDiff >= 3 => 'up',
            $diff <= -$step || $secondaryDiff <= -3 => 'down',
            default => 'steady',
        };
    }
}
