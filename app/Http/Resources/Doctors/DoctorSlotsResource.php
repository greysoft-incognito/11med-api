<?php

namespace App\Http\Resources\Doctors;

use App\Http\Resources\ApiResource;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/**
 * `zDoctorSlots`: whether the doctor publishes hours at all, the slot length, and what is free.
 *
 * @property array{has_schedule: bool, slot_minutes: int, slots: list<array{starts_at: CarbonInterface, ends_at: CarbonInterface}>} $resource
 */
class DoctorSlotsResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'has_schedule' => $this->resource['has_schedule'],
            'slot_minutes' => $this->resource['slot_minutes'],
            'slots' => array_map(fn(array $slot) => [
                'starts_at' => $this->iso($slot['starts_at']),
                'ends_at' => $this->iso($slot['ends_at']),
            ], $this->resource['slots']),
        ];
    }
}
