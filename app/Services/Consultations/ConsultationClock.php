<?php

namespace App\Services\Consultations;

use App\Models\Consultation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The consultation deadlines and windows (app `src/constants/consultations.ts`, handover §13):
 * five minutes to accept a booking for now, the sooner of a day and an hour before for a slot,
 * then ten minutes to join, counted from the slot when there is one.
 */
class ConsultationClock
{
    public function acceptBy(CarbonInterface $bookedAt, ?CarbonInterface $slot): CarbonImmutable
    {
        $bookedAt = CarbonImmutable::instance($bookedAt);

        if ($slot === null) {
            return $bookedAt->addMinutes($this->setting('accept_minutes'));
        }

        return $bookedAt
            ->addHours($this->setting('scheduled_accept_max_hours'))
            ->min(CarbonImmutable::instance($slot)->subMinutes($this->setting('scheduled_accept_before_minutes')));
    }

    public function joinBy(Consultation $consultation, CarbonInterface $now): CarbonImmutable
    {
        $from = CarbonImmutable::instance($now);

        if ($consultation->scheduled) {
            $from = $from->max(CarbonImmutable::instance($consultation->scheduled_for));
        }

        return $from->addMinutes($this->setting('join_minutes'));
    }

    /**
     * A scheduled session opens a few minutes early; a session for now at once.
     */
    public function canStart(Consultation $consultation, CarbonInterface $now): bool
    {
        return ! $consultation->scheduled
            || $now->greaterThanOrEqualTo($consultation->scheduled_for->copy()->subMinutes($this->setting('early_start_minutes')));
    }

    /**
     * A slot can be given back until the hour before it.
     */
    public function canCancel(Consultation $consultation, CarbonInterface $now): bool
    {
        return ! $consultation->scheduled
            || $now->lessThan($consultation->scheduled_for->copy()->subMinutes($this->setting('cancel_cutoff_minutes')));
    }

    /**
     * When an open chat closes itself.
     */
    public function chatClosesAt(Consultation $consultation): CarbonImmutable
    {
        return CarbonImmutable::instance($consultation->started_at)->addMinutes($consultation->length_minutes);
    }

    private function setting(string $key): int
    {
        return (int) config("elevenmed.consultations.{$key}");
    }
}
