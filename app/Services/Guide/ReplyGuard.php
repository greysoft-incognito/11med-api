<?php

namespace App\Services\Guide;

use App\Enums\GuideRoute;
use App\Enums\GuideUrgency;

/**
 * The contract's safety boundary on the server side (app handover §11): only allow-listed actions
 * survive, a test is offered only if the catalogue holds it, and an emergency always leads with
 * the emergency line, whatever wrote the reply.
 */
class ReplyGuard
{
    public const EMERGENCY_NUMBER = '112';

    public function __construct(
        private readonly TestCatalogue $catalogue,
    ) {}

    public function check(GuideReply $reply): GuideReply
    {
        $actions = array_values(array_filter($reply->actions, $this->allowed(...)));
        $known = $this->catalogue->known(array_values(array_map(
            fn(array $action) => $action['test_code'],
            array_filter($actions, fn(array $action) => $action['kind'] === 'find_test'),
        )));

        $actions = array_values(array_filter(
            $actions,
            fn(array $action) => $action['kind'] !== 'find_test' || in_array($action['test_code'], $known, true)
        ));

        if ($reply->urgency === GuideUrgency::Emergency) {
            $actions = $this->emergencyFirst($actions);
        }

        return $reply->withActions($actions);
    }

    /**
     * @param  array<string, string>  $action
     */
    private function allowed(array $action): bool
    {
        if (! isset($action['label']) || trim($action['label']) === '') {
            return false;
        }

        return match ($action['kind'] ?? null) {
            'see_specialty' => in_array($action['specialty'] ?? null, GuideAction::SPECIALTIES, true),
            'open_doctor' => is_string($action['doctor_id'] ?? null) && preg_match('/^[0-9a-f-]{36}$/i', $action['doctor_id']) === 1,
            'find_test' => ($action['test_code'] ?? '') !== '',
            'search_pharmacy' => ($action['query'] ?? '') !== '',
            'open' => GuideRoute::tryFrom($action['route'] ?? '') !== null,
            'call' => ($action['number'] ?? null) === self::EMERGENCY_NUMBER,
            default => false,
        };
    }

    /**
     * @param  list<array<string, string>>  $actions
     * @return list<array<string, string>>
     */
    private function emergencyFirst(array $actions): array
    {
        $others = array_filter($actions, fn(array $action) => $action['kind'] !== 'call');

        return [GuideAction::call(self::EMERGENCY_NUMBER, 'Call 112'), ...array_values($others)];
    }
}
