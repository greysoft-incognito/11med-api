<?php

namespace App\Enums;

/**
 * The kinds of account a person holds (`account_kinds`), any number at once. Admin access is not
 * a kind: it is staff roles and permissions (`StaffAccess`).
 */
enum AccountKind: string
{
    case Patient = 'patient';
    case Doctor = 'doctor';
    case Pharmacy = 'pharmacy';
    case Laboratory = 'laboratory';

    /**
     * The mobile app's kinds: phone accounts with OTP.
     *
     * @return list<self>
     */
    public static function app(): array
    {
        return [self::Patient, self::Doctor];
    }

    /**
     * The partner portals' kinds, members of an organisation.
     *
     * @return list<self>
     */
    public static function portal(): array
    {
        return [self::Pharmacy, self::Laboratory];
    }

    public function isApp(): bool
    {
        return in_array($this, self::app(), true);
    }

    /**
     * The client a session for this kind is issued to.
     */
    public function client(): AuthClient
    {
        return $this->isApp() ? AuthClient::App : AuthClient::Portal;
    }

    /**
     * @param  list<self>|null  $kinds
     * @return list<string>
     */
    public static function values(?array $kinds = null): array
    {
        return array_map(fn(self $kind) => $kind->value, $kinds ?? self::cases());
    }
}
