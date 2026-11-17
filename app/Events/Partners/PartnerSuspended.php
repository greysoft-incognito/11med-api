<?php

namespace App\Events\Partners;

use App\Models\Organisation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched inside the review transaction; the Pharmacy and Laboratories modules listen.
 */
class PartnerSuspended
{
    use Dispatchable;

    public function __construct(
        public readonly Organisation $organisation,
    ) {}
}
