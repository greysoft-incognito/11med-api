<?php

namespace App\Enums;

/**
 * A laboratory booking's stage (`LAB_BOOKING_STATUSES`). The laboratory moves it one step at a
 * time along `FLOW`; cancelled is off the path.
 */
enum LabBookingStatus: string
{
    case Booked = 'booked';
    case SampleCollected = 'sample_collected';
    case Processing = 'processing';
    case ResultsReady = 'results_ready';
    case Cancelled = 'cancelled';

    public const FLOW = [
        self::Booked,
        self::SampleCollected,
        self::Processing,
        self::ResultsReady,
    ];

    /**
     * The stage after this one, or null at the end of the path or off it.
     */
    public function next(): ?self
    {
        $at = array_search($this, self::FLOW, true);

        return $at === false ? null : (self::FLOW[$at + 1] ?? null);
    }

    /**
     * Still with the laboratory: booked, sample taken or processing.
     *
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::Booked->value, self::SampleCollected->value, self::Processing->value];
    }
}
