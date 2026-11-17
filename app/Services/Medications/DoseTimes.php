<?php

namespace App\Services\Medications;

use App\Models\Medication;
use App\Support\LocalDay;
use Carbon\CarbonImmutable;

/**
 * The instants a schedule's doses fall at: each `specific_times` entry, read in Lagos, on every day
 * from `start_date` to `end_date` (FE-002). A paused schedule has none.
 */
final class DoseTimes
{
    /**
     * The dose instants after `$after` and up to and including `$until`, oldest first.
     *
     * @return list<CarbonImmutable>
     */
    public static function between(Medication $medication, CarbonImmutable $after, CarbonImmutable $until): array
    {
        if ($medication->paused_at !== null || $until->lessThanOrEqualTo($after)) {
            return [];
        }

        $first = LocalDay::containing($after)->max(LocalDay::of($medication->start_date->toDateString()));
        $last = LocalDay::containing($until);

        if ($medication->end_date !== null) {
            $last = $last->min(LocalDay::of($medication->end_date->toDateString()));
        }

        $times = self::times($medication);
        $instants = [];

        for ($day = $first; $day->lessThanOrEqualTo($last); $day = $day->addDay()) {
            foreach ($times as [$hour, $minute]) {
                $instant = $day
                    ->setTime($hour, $minute)
                    ->utc();

                if ($instant->greaterThan($after) && $instant->lessThanOrEqualTo($until)) {
                    $instants[] = $instant;
                }
            }
        }

        return $instants;
    }

    /**
     * Whether `$instant` is one of the schedule's dose times, paused or not.
     */
    public static function isDoseOf(Medication $medication, CarbonImmutable $instant): bool
    {
        $local = $instant->setTimezone(LocalDay::zone());
        $date = $local->toDateString();

        if ($local->second !== 0 || $local->micro !== 0 || $date < $medication->start_date->toDateString()) {
            return false;
        }

        if ($medication->end_date !== null && $date > $medication->end_date->toDateString()) {
            return false;
        }

        return in_array($local->format('H:i'), $medication->specific_times, true);
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private static function times(Medication $medication): array
    {
        $times = array_unique($medication->specific_times);
        sort($times);

        return array_map(fn(string $time) => array_map('intval', explode(':', $time)), $times);
    }
}
