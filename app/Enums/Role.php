<?php

namespace App\Enums;

/**
 * The five roles, as Spatie roles. A user holds exactly one.
 */
enum Role: string
{
    case Patient = 'patient';
    case Doctor = 'doctor';
    case Pharmacy = 'pharmacy';
    case Laboratory = 'laboratory';
    case Admin = 'admin';

    /**
     * The mobile app's roles: phone accounts with OTP.
     *
     * @return list<self>
     */
    public static function app(): array
    {
        return [self::Patient, self::Doctor];
    }

    /**
     * The portals' roles: email accounts without a phone.
     *
     * @return list<self>
     */
    public static function portal(): array
    {
        return [self::Pharmacy, self::Laboratory, self::Admin];
    }

    public function isApp(): bool
    {
        return in_array($this, self::app(), true);
    }

    /**
     * @param  list<self>|null  $roles
     * @return list<string>
     */
    public static function values(?array $roles = null): array
    {
        return array_map(fn(self $role) => $role->value, $roles ?? self::cases());
    }
}
