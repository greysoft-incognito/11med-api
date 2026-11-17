<?php

namespace App\Http\Requests\Laboratories;

/**
 * Rules the laboratory request schemas share.
 */
final class LabRules
{
    public const TIME_OF_DAY = '/^([01]\d|2[0-3]):[0-5]\d$/';

    /**
     * `zKobo`: a whole, non-negative number of kobo.
     *
     * @return list<string>
     */
    public static function kobo(): array
    {
        return ['integer', 'min:0', 'max:' . PHP_INT_MAX];
    }

    /**
     * `zTimeOfDay`: `HH:MM`, 24-hour.
     *
     * @return list<string>
     */
    public static function timeOfDay(): array
    {
        return ['required', 'string', 'regex:' . self::TIME_OF_DAY];
    }
}
