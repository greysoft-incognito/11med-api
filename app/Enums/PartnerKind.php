<?php

namespace App\Enums;

/**
 * What a partner is (portal `PARTNER_KINDS`): its portal role has the same name.
 */
enum PartnerKind: string
{
    case Pharmacy = 'pharmacy';
    case Laboratory = 'laboratory';

    public function role(): Role
    {
        return Role::from($this->value);
    }
}
