<?php

namespace App\Services\Consultations;

use App\Enums\ConsultationStatus;
use App\Enums\NotificationCategory;
use App\Models\Consultation;
use App\Services\Notifications\Notifier;
use App\Services\Payments\Payments;
use Illuminate\Support\Facades\DB;

/**
 * A booking's payment clearing (app handover §16): from then the doctor sees it and is told, with
 * the acceptance deadline. A payment that clears after the booking lapsed is given back.
 */
class PaidConsultations
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly Payments $payments,
    ) {}

    public function settle(Consultation $consultation): void
    {
        $paid = DB::transaction(function () use ($consultation) {
            $current = Consultation::query()
                ->whereKey($consultation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($current->paid_at !== null) {
                return null;
            }

            $current->paid_at = now();
            $current->save();

            return $current;
        });

        if ($paid === null) {
            return;
        }

        if ($paid->status !== ConsultationStatus::Pending) {
            $this->payments->refund($paid);

            return;
        }

        $this->tellDoctor($paid);
    }

    private function tellDoctor(Consultation $consultation): void
    {
        $doctor = $consultation->doctor;

        if ($doctor === null) {
            return;
        }

        $deadline = $consultation->expires_at
            ->copy()
            ->setTimezone(config('elevenmed.timezone'))
            ->format('H:i');

        $this->notifier->send(
            $doctor,
            NotificationCategory::Consultation,
            __('consultations.requested.title'),
            __('consultations.requested.body', ['deadline' => $deadline]),
            [
                'target_id' => $consultation->uuid,
                'dedupe_key' => "consultation_requested:{$consultation->uuid}",
            ]
        );
    }
}
