<?php

namespace App\Enums;

/**
 * What a partner is (portal `PARTNER_KINDS`): its members hold the account kind of the same name.
 */
enum PartnerKind: string
{
    case Pharmacy = 'pharmacy';
    case Laboratory = 'laboratory';

    public function accountKind(): AccountKind
    {
        return AccountKind::from($this->value);
    }
}
