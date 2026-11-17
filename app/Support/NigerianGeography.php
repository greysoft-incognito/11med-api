<?php

namespace App\Support;

/**
 * The 36 states, the FCT and their 774 local government areas, spelled as the app bundles them
 * (`resources/data/nigerian-lgas.php`). The profile's `state_of_residence` and `lga` are checked here.
 */
final class NigerianGeography
{
    /**
     * @var array<string, list<string>>|null
     */
    private static ?array $lgas = null;

    /**
     * @return list<string>
     */
    public static function states(): array
    {
        return array_keys(self::all());
    }

    public static function isLgaOf(string $state, string $lga): bool
    {
        return in_array($lga, self::all()[$state] ?? [], true);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function all(): array
    {
        return self::$lgas ??= require resource_path('data/nigerian-lgas.php');
    }
}
