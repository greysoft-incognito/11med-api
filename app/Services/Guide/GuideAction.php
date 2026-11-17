<?php

namespace App\Services\Guide;

use App\Enums\GuideRoute;

/**
 * The six `zGuideAction` kinds. Everything actionable in a reply is built here, so a reply can only
 * lead somewhere a person could have navigated themselves.
 */
final class GuideAction
{
    public const SPECIALTIES = [
        'general_practice',
        'cardiology',
        'endocrinology',
        'nephrology',
        'obstetrics',
        'paediatrics',
        'psychiatry',
        'other',
    ];

    /**
     * @return array{kind: 'see_specialty', specialty: string, label: string}
     */
    public static function specialty(string $specialty, string $label): array
    {
        return ['kind' => 'see_specialty', 'specialty' => $specialty, 'label' => $label];
    }

    /**
     * @return array{kind: 'find_test', test_code: string, label: string}
     */
    public static function test(string $code, string $label): array
    {
        return ['kind' => 'find_test', 'test_code' => $code, 'label' => $label];
    }

    /**
     * @return array{kind: 'search_pharmacy', query: string, label: string}
     */
    public static function store(string $query, string $label): array
    {
        return ['kind' => 'search_pharmacy', 'query' => $query, 'label' => $label];
    }

    /**
     * @return array{kind: 'open', route: string, label: string}
     */
    public static function open(GuideRoute $route, string $label): array
    {
        return ['kind' => 'open', 'route' => $route->value, 'label' => $label];
    }

    /**
     * @return array{kind: 'call', number: string, label: string}
     */
    public static function call(string $number, string $label): array
    {
        return ['kind' => 'call', 'number' => $number, 'label' => $label];
    }
}
