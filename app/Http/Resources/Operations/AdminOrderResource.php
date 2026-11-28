<?php

namespace App\Http\Resources\Operations;

use App\Http\Resources\Pharmacy\PharmacyOrderResource;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Portal `zAdminOrder`: the pharmacy's view of an order, with which pharmacy and which patient.
 *
 * @property Order $resource
 */
class AdminOrderResource extends PharmacyOrderResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'pharmacy_name' => $this->resource->organisation->name,
            'patient_id' => $this->resource->patient->uuid,
        ];
    }
}
