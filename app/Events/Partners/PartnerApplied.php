<?php

namespace App\Events\Partners;

use App\Models\Organisation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A pharmacy or laboratory applied and waits for the team's decision.
 */
class PartnerApplied implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Organisation $organisation,
    ) {}
}
