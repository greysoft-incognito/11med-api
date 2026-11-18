<?php

namespace Database\Seeders\Demo;

use App\Models\Reading;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The app mock's month of home readings (`seed.ts` `demoReadings`): morning pressures rising
 * into grade 1 over the last week, a few sugars, and a weight each fortnight.
 */
class DemoReadings
{
    private const PRESSURES = [
        [134, 86, 74], [131, 84, 71], [136, 88, 76], [129, 83, 70], [133, 85, 73], [137, 87, 77],
        [130, 84, 72], [135, 86, 75], [132, 85, 71], [138, 88, 78], [131, 83, 72], [134, 86, 74],
        [136, 87, 76], [133, 85, 73], [139, 89, 77], [135, 86, 74], [132, 84, 71], [137, 88, 75],
        [144, 91, 79], [147, 93, 81], [143, 90, 78], [149, 95, 82], [146, 92, 80], [148, 94, 83],
    ];

    private const SUGARS = [
        [27, 5.8, 'fasting'], [21, 6.3, 'fasting'], [16, 5.6, 'fasting'], [12, 8.4, 'after_meal'],
        [9, 6.0, 'fasting'], [5, 6.4, 'fasting'], [2, 5.9, 'fasting'],
    ];

    private const WEIGHTS = [[28, 74.5], [14, 74.0], [1, 73.6]];

    public static function create(User $user): void
    {
        $at = fn(int $daysBack, int $hour, int $minute) => CarbonImmutable::today(config('elevenmed.timezone'))
            ->subDays($daysBack)
            ->setTime($hour, $minute);

        $day = 30;

        foreach (self::PRESSURES as [$systolic, $diastolic, $pulse]) {
            self::reading($user, ['type' => 'blood_pressure', 'systolic' => $systolic, 'diastolic' => $diastolic, 'pulse' => $pulse], $at($day, 7, 40 + ($day % 3) * 5));

            $day -= $day % 4 === 1 ? 2 : 1;

            if ($day < 1) {
                break;
            }
        }

        foreach (self::SUGARS as [$daysBack, $mmol, $context]) {
            self::reading($user, ['type' => 'blood_glucose', 'mmol_l' => $mmol, 'context' => $context], $at($daysBack, $context === 'fasting' ? 7 : 14, 10));
        }

        foreach (self::WEIGHTS as [$daysBack, $kg]) {
            self::reading($user, ['type' => 'weight', 'kg' => $kg], $at($daysBack, 8, 0));
        }
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private static function reading(User $user, array $fields, CarbonImmutable $takenAt): void
    {
        $reading = new Reading(['user_id' => $user->id, 'taken_at' => $takenAt->utc(), ...$fields]);
        $reading->created_at = $takenAt->addMinutes(5);
        $reading->save();
    }
}
