<?php

namespace App\Http\Resources\Caregiving;

use App\Http\Resources\ApiResource;
use App\Models\Caregiver;
use Illuminate\Http\Request;

/**
 * `zCaregiver`.
 *
 * @property Caregiver $resource
 */
class CaregiverResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->uuid,
            'name' => $this->resource->caregiver?->full_name,
            'phone' => $this->resource->phone,
            'status' => $this->resource->status->value,
            'invited_at' => $this->iso($this->resource->invited_at),
            'invite_code' => $this->resource->invite_code,
        ];
    }
}
