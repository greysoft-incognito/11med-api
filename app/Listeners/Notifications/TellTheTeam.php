<?php

namespace App\Listeners\Notifications;

use App\Enums\AdminPermission;
use App\Enums\NotificationCategory;
use App\Events\Doctors\DoctorSubmitted;
use App\Events\Partners\PartnerApplied;
use App\Events\Payments\RefundFailed;
use App\Services\Notifications\Notifier;

/**
 * The team's alerts, each to the admins whose permission covers acting on it.
 */
class TellTheTeam
{
    public function __construct(
        private readonly Notifier $notifier,
    ) {}

    public function handleDoctorSubmitted(DoctorSubmitted $event): void
    {
        $profile = $event->profile;

        $this->notifier->toPermission(
            AdminPermission::DoctorsReview,
            NotificationCategory::DoctorReview,
            __('notifications.team.doctor_submitted.title'),
            __('notifications.team.doctor_submitted.body', ['name' => $profile->user->full_name]),
            [
                'target_id' => $profile->user->uuid,
                'dedupe_key' => "doctor_submitted:{$profile->user->uuid}:{$profile->submitted_at->getTimestamp()}",
            ],
        );
    }

    public function handlePartnerApplied(PartnerApplied $event): void
    {
        $organisation = $event->organisation;

        $this->notifier->toPermission(
            AdminPermission::PartnersReview,
            NotificationCategory::PartnerApplication,
            __("notifications.team.partner_applied.{$organisation->kind->value}_title"),
            __('notifications.team.partner_applied.body', ['name' => $organisation->name]),
            ['target_id' => $organisation->uuid, 'dedupe_key' => "partner_applied:{$organisation->uuid}"],
        );
    }

    public function handleRefundFailed(RefundFailed $event): void
    {
        $payment = $event->refund->payment;

        $this->notifier->toPermission(
            AdminPermission::PaymentsView,
            NotificationCategory::PaymentAlert,
            __('notifications.team.refund_failed.title'),
            __('notifications.team.refund_failed.body', ['reference' => $payment->reference]),
            ['target_id' => $payment->uuid, 'dedupe_key' => "refund_failed:{$event->refund->uuid}"],
        );
    }
}
