<?php

namespace App\Http\Resources\Partners;

use App\Http\Resources\ApiResource;
use App\Models\Organisation;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\Request;

/**
 * `zPartner`. The licence is a signed link; the live API never hands back the welcome link.
 *
 * @property Organisation $resource
 */
class PartnerResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $partner = $this->resource;
        $licence = $partner->licence;

        return [
            'id' => $partner->uuid,
            'kind' => $partner->kind->value,
            'name' => $partner->name,
            'state' => $partner->state,
            'city' => $partner->city,
            'address' => $partner->address,
            'licence_number' => $partner->licence_number,
            'licence_url' => $licence === null ? null : app(MediaLibrary::class)->url($licence),
            'contact_name' => $partner->contact_name,
            'contact_phone' => $partner->contact_phone,
            'contact_email' => $partner->contact_email,
            'status' => $partner->status->value,
            'notes' => $partner->notes,
            'applied_at' => $this->iso($partner->applied_at),
            'reviewed_at' => $this->iso($partner->reviewed_at),
            'dev_set_password_link' => null,
        ];
    }
}
