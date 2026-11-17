<?php

namespace App\Listeners\Pharmacy;

use App\Enums\PartnerKind;
use App\Events\Partners\PartnerApproved;
use App\Models\Pharmacy;

/**
 * An approved pharmacy gets its store settings, with the default delivery fee it then edits.
 */
class OpenPharmacyStore
{
    public function handle(PartnerApproved $event): void
    {
        if ($event->organisation->kind !== PartnerKind::Pharmacy) {
            return;
        }

        Pharmacy::query()->firstOrCreate(
            ['organisation_id' => $event->organisation->id],
            ['delivery_fee_kobo' => Pharmacy::DEFAULT_DELIVERY_FEE_KOBO]
        );
    }
}
