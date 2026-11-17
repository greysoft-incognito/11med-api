<?php

namespace App\Services\Medications;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

/**
 * A dose's id (app `zDoseId`): `<medication uuid>:<scheduled_for>`, split on the first colon only,
 * since the instant carries colons of its own.
 */
final readonly class DoseId
{
    public function __construct(
        public string $medicationId,
        public CarbonImmutable $scheduledFor,
    ) {}

    public static function parse(string $id): ?self
    {
        $separator = strpos($id, ':');

        if ($separator === false) {
            return null;
        }

        $medicationId = substr($id, 0, $separator);
        $instant = substr($id, $separator + 1);

        if (! Str::isUuid($medicationId) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})$/', $instant)) {
            return null;
        }

        try {
            $scheduledFor = CarbonImmutable::parse($instant)->utc();
        } catch (Throwable) {
            return null;
        }

        return new self(strtolower($medicationId), $scheduledFor);
    }

    public static function compose(string $medicationId, CarbonImmutable $scheduledFor): string
    {
        return $medicationId . ':' . $scheduledFor
            ->utc()
            ->toIso8601ZuluString('millisecond');
    }

    public function __toString(): string
    {
        return self::compose($this->medicationId, $this->scheduledFor);
    }
}
