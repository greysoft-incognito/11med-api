<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Calendar days as the patient lives them, in Africa/Lagos (`elevenmed.timezone`). Instants stay UTC.
 */
final class LocalDay
{
    public static function zone(): string
    {
        return config('elevenmed.timezone');
    }

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::zone())->startOfDay();
    }

    /**
     * Midnight at the start of `YYYY-MM-DD`, in Lagos.
     */
    public static function of(string $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $date, self::zone());
    }

    /**
     * The Lagos day an instant falls on.
     */
    public static function containing(CarbonImmutable $instant): CarbonImmutable
    {
        return $instant
            ->setTimezone(self::zone())
            ->startOfDay();
    }
}
