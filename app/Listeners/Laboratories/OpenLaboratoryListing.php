<?php

namespace App\Listeners\Laboratories;

use App\Enums\PartnerKind;
use App\Events\Partners\PartnerApproved;
use App\Services\Laboratories\Laboratories;

/**
 * An approved laboratory gets its listing, with default hours and no home collection.
 */
class OpenLaboratoryListing
{
    public function __construct(
        private readonly Laboratories $laboratories,
    ) {}

    public function handle(PartnerApproved $event): void
    {
        if ($event->organisation->kind === PartnerKind::Laboratory) {
            $this->laboratories->open($event->organisation);
        }
    }
}
