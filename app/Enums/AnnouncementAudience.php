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
     * The account kinds it reaches.
     *
     * @return list<AccountKind>
     */
    public function kinds(): array
    {
        return match ($this) {
            self::AppUsers => AccountKind::app(),
            self::Patients => [AccountKind::Patient],
            self::Doctors => [AccountKind::Doctor],
            self::Pharmacies => [AccountKind::Pharmacy],
            self::Laboratories => [AccountKind::Laboratory],
        };
    }
}
