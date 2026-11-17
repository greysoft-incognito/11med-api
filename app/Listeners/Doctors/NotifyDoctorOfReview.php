<?php

namespace App\Listeners\Doctors;

use App\Enums\DocumentStatus;
use App\Enums\NotificationCategory;
use App\Enums\VerificationStatus;
use App\Events\Doctors\DoctorDocumentReviewed;
use App\Events\Doctors\DoctorReviewed;
use App\Services\Notifications\Notifier;

/**
 * Tells a doctor the team decided on their application, or that a document needs replacing
 * (portal handover §6 "Doctors"). An accepted document is not pushed: the decision follows.
 */
class NotifyDoctorOfReview
{
    public function __construct(
        private readonly Notifier $notifier,
    ) {}

    public function handleDoctorReviewed(DoctorReviewed $event): void
    {
        $profile = $event->profile;
        $reinstated = $profile->verification_status === VerificationStatus::Approved && $event->previous === VerificationStatus::Suspended;
        $outcome = $reinstated ? 'reinstated' : $profile->verification_status->value;

        $this->notifier->send(
            $profile->user,
            NotificationCategory::Account,
            __("notifications.doctor_review.{$outcome}.title"),
            __("notifications.doctor_review.{$outcome}.body"),
            ['dedupe_key' => "doctor_review:{$profile->uuid}:{$profile->reviewed_at?->getTimestamp()}"],
        );
    }

    public function handleDoctorDocumentReviewed(DoctorDocumentReviewed $event): void
    {
        $document = $event->document;

        if ($document->status !== DocumentStatus::Rejected) {
            return;
        }

        $this->notifier->send(
            $document->profile->user,
            NotificationCategory::Account,
            __('notifications.doctor_document_rejected.title'),
            __('notifications.doctor_document_rejected.body'),
            ['dedupe_key' => "doctor_document:{$document->uuid}:{$document->reviewed_at?->getTimestamp()}"],
        );
    }
}
