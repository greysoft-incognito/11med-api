<?php

namespace App\Enums;

/**
 * Who an announcement goes to (portal `zAnnouncementAudience`).
 */
enum AnnouncementAudience: string
{
    case AppUsers = 'app_users';
    case Patients = 'patients';
    case Doctors = 'doctors';
    case Pharmacies = 'pharmacies';
    case Laboratories = 'laboratories';

    /**
     * @return list<Role>
     */
    public function roles(): array
    {
        return match ($this) {
            self::AppUsers => Role::app(),
            self::Patients => [Role::Patient],
            self::Doctors => [Role::Doctor],
            self::Pharmacies => [Role::Pharmacy],
            self::Laboratories => [Role::Laboratory],
        };
    }
}
