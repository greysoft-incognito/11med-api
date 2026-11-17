<?php

namespace App\Services\Guide;

use App\Enums\GuideRoute;

/**
 * Questions about the app itself, each answered with one screen (the app mock's rules).
 */
final class PlatformRules
{
    private const CAREGIVER = [
        'caregiver', 'care ?giver', "my (mother|father|mum|mom|dad|parent|grandm|grandf|wife|husband)('s)? (medicine|medication|drugs|doses)",
        'add my (mother|father|mum|mom|dad|parent|grand)', 'see (her|his|their) (medicine|medication|drugs|doses)', 'look after',
        'keep an eye on', 'watch (over )?(my|their)',
    ];

    private const QR = ['\bqr\b', 'emergency (card|profile|record|code)'];

    private const NOTIFICATIONS = ['notification', 'alerts?\b', 'stop (the )?(messages|reminders)'];

    private const PROFILE = ['my (medical )?profile', 'my allerg', 'update my', 'change my (blood|genotype|state|contact)'];

    public function early(string $text): ?GuideReply
    {
        if (Patterns::any(self::CAREGIVER, $text)) {
            return $this->screen(
                'You can invite someone to watch your medication, or accept an invitation to watch theirs. '
                    . 'A caregiver sees the schedule and whether doses were taken, and nothing else.',
                GuideRoute::Caregivers,
                'Manage caregivers',
            );
        }

        if (Patterns::any(self::QR, $text)) {
            return $this->screen(
                'Your emergency QR gives a hospital your blood type, genotype, allergies, conditions and current medications '
                    . 'when you cannot speak for yourself. It is on your profile.',
                GuideRoute::EmergencyQr,
                'Show my emergency QR',
            );
        }

        if (Patterns::any(self::NOTIFICATIONS, $text)) {
            return $this->screen(
                'Each kind of notification can be switched on or off separately: dose reminders, missed doses, refills, consultations and the rest.',
                GuideRoute::Notifications,
                'Notification settings',
            );
        }

        return null;
    }

    public function profile(string $text): ?GuideReply
    {
        if (! Patterns::any(self::PROFILE, $text)) {
            return null;
        }

        return $this->screen(
            'Your medical profile holds your blood type, genotype, allergies, conditions and emergency contact. '
                . 'It is what a doctor sees when you share it, and what the emergency QR shows.',
            GuideRoute::Profile,
            'Open my profile',
        );
    }

    private function screen(string $body, GuideRoute $route, string $label): GuideReply
    {
        return new GuideReply($body, [GuideAction::open($route, $label)]);
    }
}
