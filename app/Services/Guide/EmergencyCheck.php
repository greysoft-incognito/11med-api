<?php

namespace App\Services\Guide;

use App\Enums\GuideRoute;
use App\Enums\GuideUrgency;

/**
 * Runs before anything else writes a reply, and wins: a message that could describe an emergency
 * is answered with the emergency line, never with a booking page (app handover §11).
 */
class EmergencyCheck
{
    private const PATTERNS = [
        'chest pain', 'chest tight', "can'?t breathe", 'cannot breathe', 'difficulty breathing', 'struggling to breathe',
        'unconscious', 'passed out', 'not waking', 'seizure', 'convuls', 'fitting',
        'stroke', 'face droop', 'slurred', 'one side (of my body )?(is )?(weak|numb)',
        'heavy bleeding', "bleeding (a lot|heavily|won'?t stop)", 'vomiting blood', 'coughing (up )?blood',
        'suicid', 'kill myself', 'end my life', 'overdose', 'poison', 'swallowed',
        'anaphyla', 'throat (is )?(closing|swelling)', 'lips (are )?swelling',
        'snake ?bite', 'severe burn', 'stiff neck', 'labou?r pains', 'water(s)? (has|have) broke',
    ];

    /**
     * @param  string  $text  normalised by `Patterns::normalise`
     */
    public function matches(string $text): bool
    {
        return Patterns::any(self::PATTERNS, $text);
    }

    public function reply(): GuideReply
    {
        return new GuideReply(
            'This could be an emergency. Call 112 now, or get to the nearest hospital. Do not wait for a consultation. '
                . 'If someone is with you, show them your emergency QR so the hospital has your blood type, allergies and medications.',
            [
                GuideAction::call('112', 'Call 112'),
                GuideAction::open(GuideRoute::EmergencyQr, 'Show my emergency QR'),
            ],
            GuideUrgency::Emergency,
        );
    }
}
