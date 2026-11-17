<?php

namespace App\Services\Doctors;

use App\Models\DoctorProfile;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Bookable slots (app handover §13): the doctor's weekly hours in Africa/Lagos, cut into sessions of
 * the doctor's length, from two hours ahead out to a week, less what is already held. Computed on
 * read, so two patients looking at once see one list.
 */
class DoctorSlots
{
    public const LEAD_MINUTES = 120;

    public const HORIZON_DAYS = 7;

    public function __construct(
        private readonly HeldSlots $held,
    ) {}

    /**
     * @return list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    public function open(DoctorProfile $profile, CarbonInterface $now): array
    {
        $offered = $this->offered($profile, $now);

        if ($offered === []) {
            return [];
        }

        $held = array_map(
            fn(CarbonInterface $at) => $at->getTimestamp(),
            $this->held->heldBetween($profile->user_id, $offered[0]['starts_at'], end($offered)['starts_at'])
        );

        return array_values(array_filter(
            $offered,
            fn(array $slot) => ! in_array($slot['starts_at']->getTimestamp(), $held, true)
        ));
    }

    /**
     * Whether `$startsAt` is a slot the hours offer now, held or not (the booking checks the hold).
     */
    public function offers(DoctorProfile $profile, CarbonInterface $startsAt, CarbonInterface $now): bool
    {
        foreach ($this->offered($profile, $now) as $slot) {
            if ($slot['starts_at']->getTimestamp() === $startsAt->getTimestamp()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    private function offered(DoctorProfile $profile, CarbonInterface $now): array
    {
        $zone = config('elevenmed.timezone');
        $length = $profile->session_minutes;
        $earliest = CarbonImmutable::instance($now)->addMinutes(self::LEAD_MINUTES);
        $today = CarbonImmutable::instance($now)
            ->setTimezone($zone)
            ->startOfDay();

        $slots = [];

        for ($d = 0; $d <= self::HORIZON_DAYS; $d++) {
            $day = $today->addDays($d);

            foreach ($profile->availability as $window) {
                if ((int) $window['day'] !== $day->dayOfWeek) {
                    continue;
                }

                $end = $this->minutes($window['end']);

                for ($m = $this->minutes($window['start']); $m + $length <= $end; $m += $length) {
                    $at = $day->addMinutes($m);

                    if ($at->lessThan($earliest)) {
                        continue;
                    }

                    $slots[$at->getTimestamp()] = ['starts_at' => $at->utc(), 'ends_at' => $at->addMinutes($length)->utc()];
                }
            }
        }

        ksort($slots);

        return array_values($slots);
    }

    private function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
