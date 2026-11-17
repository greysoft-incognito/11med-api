<?php

namespace App\Enums;

/**
 * Where a partner stands (portal `PARTNER_STATUSES`). Only an approved partner is listed in the app.
 */
enum PartnerStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Suspended = 'suspended';
}
