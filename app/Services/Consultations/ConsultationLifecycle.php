<?php

namespace App\Services\Consultations;

use App\Enums\ConsultationStatus;
use App\Enums\NotificationCategory;
use App\Exceptions\ApiException;
use App\Models\Consultation;
use App\Services\Notifications\Notifier;
use App\Services\Payments\Payments;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * The only code that changes a consultation's status (app spec §7.2, §7.7). Each move locks the row,
 * checks where it stands and refuses with the contract's code. Every way a booking ends unserved
 * (declined, expired, cancelled) refunds it in full.
 */
class ConsultationLifecycle
{
    public function __construct(
        private readonly ConsultationClock $clock,
        private readonly Payments $payments,
        private readonly Notifier $notifier,
        private readonly CallRooms $rooms,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function open(array $attributes): Consultation
    {
        return Consultation::create(['status' => ConsultationStatus::Pending] + $attributes);
    }

    public function accept(Consultation $consultation): Consultation
    {
        $consultation = $this->move($consultation, function (Consultation $current) {
            if ($current->status !== ConsultationStatus::Pending) {
                throw ApiException::unprocessable('NOT_PENDING');
            }

            $current->status = ConsultationStatus::Confirmed;
            $current->expires_at = $this->clock->joinBy($current, now());
        });

        $this->tellPatient($consultation, 'accepted', __('consultations.accepted.title', ['doctor' => $consultation->doctor_name]), __('consultations.accepted.body'));

        return $consultation;
    }

    public function decline(Consultation $consultation): Consultation
    {
        $consultation = $this->move($consultation, function (Consultation $current) {
            if ($current->status !== ConsultationStatus::Pending) {
                throw ApiException::unprocessable('NOT_PENDING');
            }

            $current->status = ConsultationStatus::Cancelled;
            $current->expires_at = null;
        });

        $this->refund($consultation);
        $this->tellPatient($consultation, 'declined', __('consultations.declined.title', ['doctor' => $consultation->doctor_name]), __('consultations.declined.body'));

        return $consultation;
    }

    public function start(Consultation $consultation): Consultation
    {
        return $this->move($consultation, function (Consultation $current) {
            if ($current->status !== ConsultationStatus::Confirmed) {
                throw ApiException::unprocessable('NOT_CONFIRMED');
            }

            if (! $this->clock->canStart($current, now())) {
                throw ApiException::unprocessable('NOT_YET');
            }

            $current->status = ConsultationStatus::InProgress;
            $current->started_at = now();
            $current->expires_at = null;
        });
    }

    public function end(Consultation $consultation): Consultation
    {
        $consultation = $this->move($consultation, function (Consultation $current) {
            if ($current->status !== ConsultationStatus::InProgress) {
                throw ApiException::unprocessable('NOT_ACTIVE');
            }

            $current->status = ConsultationStatus::Completed;
            $current->ended_at = now();
        });

        $this->rooms->close($consultation);

        return $consultation;
    }

    /**
     * The patient's cancellation, allowed until the session starts, and for a slot until the hour
     * before it.
     */
    public function cancel(Consultation $consultation): Consultation
    {
        $consultation = $this->move($consultation, function (Consultation $current) {
            if (in_array($current->status, [ConsultationStatus::InProgress, ConsultationStatus::Completed], true)) {
                throw ApiException::unprocessable('CONSULTATION_IN_PROGRESS');
            }

            if ($current->status->isClosed()) {
                throw ApiException::unprocessable('CONSULTATION_CLOSED');
            }

            if (! $this->clock->canCancel($current, now())) {
                throw ApiException::unprocessable('TOO_LATE_TO_CANCEL');
            }

            $current->status = ConsultationStatus::Cancelled;
            $current->expires_at = null;
        });

        $this->refund($consultation);
        $this->tellDoctor($consultation, 'patient_cancelled');

        return $consultation;
    }

    /**
     * The team's cancellation of a booking that has not begun, whatever the hour: refunded in full,
     * and both sides told.
     */
    public function cancelForTeam(Consultation $consultation): Consultation
    {
        $consultation = $this->move($consultation, function (Consultation $current) {
            if (in_array($current->status, [ConsultationStatus::InProgress, ConsultationStatus::Completed], true)) {
                throw ApiException::unprocessable('CONSULTATION_IN_PROGRESS');
            }

            if ($current->status->isClosed()) {
                throw ApiException::unprocessable('CONSULTATION_CLOSED');
            }

            $current->status = ConsultationStatus::Cancelled;
            $current->expires_at = null;
        });

        $this->refund($consultation);
        $this->tellPatient($consultation, 'team_cancelled', __('consultations.team_cancelled.title'), __('consultations.team_cancelled.body', ['doctor' => $consultation->doctor_name]));

        if ($consultation->doctor !== null && $consultation->paid_at !== null) {
            $this->notifier->send($consultation->doctor, NotificationCategory::Consultation, __('consultations.team_cancelled.doctor_title'), __('consultations.team_cancelled.doctor_body'), [
                'target_id' => $consultation->uuid,
                'dedupe_key' => "consultation_team_cancelled_doctor:{$consultation->uuid}",
            ]);
        }

        return $consultation;
    }

    /**
     * A deadline passed with nobody there: the scheduler's move. Returns null when the row moved on
     * since it was selected.
     */
    public function expire(Consultation $consultation): ?Consultation
    {
        $expired = DB::transaction(function () use ($consultation) {
            $current = $this->locked($consultation);

            $due = in_array($current->status, [ConsultationStatus::Pending, ConsultationStatus::Confirmed], true)
                && $current->expires_at !== null
                && $current->expires_at->lessThanOrEqualTo(now());

            if (! $due) {
                return null;
            }

            $unanswered = $current->status === ConsultationStatus::Pending;

            $current->status = ConsultationStatus::Expired;
            $current->expires_at = null;
            $current->save();

            return [$current, $unanswered];
        });

        if ($expired === null) {
            return null;
        }

        [$expired, $unanswered] = $expired;

        $this->refund($expired);

        if ($unanswered) {
            $this->tellDoctor($expired, 'request_expired');
        }

        return $expired;
    }

    /**
     * The doctor was taken off the directory before the session began: the patient is refunded and
     * told. Returns null when the session had started or closed.
     */
    public function withdraw(Consultation $consultation): ?Consultation
    {
        $withdrawn = DB::transaction(function () use ($consultation) {
            $current = $this->locked($consultation);

            if (! in_array($current->status, [ConsultationStatus::Pending, ConsultationStatus::Confirmed], true)) {
                return null;
            }

            $current->status = ConsultationStatus::Cancelled;
            $current->expires_at = null;
            $current->save();

            return $current;
        });

        if ($withdrawn !== null) {
            $this->refund($withdrawn);
            $this->tellPatient($withdrawn, 'withdrawn', __('consultations.withdrawn.title'), __('consultations.withdrawn.body', ['doctor' => $withdrawn->doctor_name]));
        }

        return $withdrawn;
    }

    /**
     * An open chat whose window ran out, ended at the moment it closed rather than when noticed.
     */
    public function closeChat(Consultation $consultation): ?Consultation
    {
        return DB::transaction(function () use ($consultation) {
            $current = $this->locked($consultation);

            if ($current->status !== ConsultationStatus::InProgress || $current->session_type->isCall()) {
                return null;
            }

            $closesAt = $this->clock->chatClosesAt($current);

            if ($closesAt->isAfter(CarbonImmutable::now())) {
                return null;
            }

            $current->status = ConsultationStatus::Completed;
            $current->ended_at = $closesAt;
            $current->save();

            return $current;
        });
    }

    /**
     * @param  Closure(Consultation): void  $change
     */
    private function move(Consultation $consultation, Closure $change): Consultation
    {
        return DB::transaction(function () use ($consultation, $change) {
            $current = $this->locked($consultation);

            $change($current);
            $current->save();

            return $current;
        });
    }

    private function locked(Consultation $consultation): Consultation
    {
        return Consultation::query()
            ->whereKey($consultation->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function refund(Consultation $consultation): void
    {
        $this->payments->refund($consultation);
    }

    /**
     * Only a doctor who was shown the request: one that was paid.
     */
    private function tellDoctor(Consultation $consultation, string $event): void
    {
        if (! $consultation->isPaid() || $consultation->doctor === null) {
            return;
        }

        $this->notifier->send($consultation->doctor, NotificationCategory::Consultation, __("consultations.{$event}.title"), __("consultations.{$event}.body"), [
            'target_id' => $consultation->uuid,
            'dedupe_key' => "consultation_{$event}:{$consultation->uuid}",
        ]);
    }

    private function tellPatient(Consultation $consultation, string $event, string $title, string $body): void
    {
        $this->notifier->send($consultation->patient, NotificationCategory::Consultation, $title, $body, [
            'target_id' => $consultation->uuid,
            'dedupe_key' => "consultation_{$event}:{$consultation->uuid}",
        ]);
    }
}
