<?php

namespace App\Http\Resources\Operations;

use App\Http\Resources\ApiResource;
use App\Http\Resources\Laboratories\LabBookingResource;
use App\Models\LabBooking;
use Illuminate\Http\Request;

/**
 * Portal `zAdminLabBooking`: the booking, the laboratory and the patient, without the results or
 * the doctor's notes.
 *
 * @property LabBooking $resource
 */
class AdminLabBookingResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $booking = $this->resource;
        $shared = LabBookingResource::shared($booking);

        unset($shared['results_url']);

        return [
            ...$shared,
            'laboratory_id' => $booking->laboratory->organisation->uuid,
            'laboratory_name' => $booking->laboratory->organisation->name,
            'patient_id' => $booking->patient->uuid,
            'patient_name' => $booking->patient->full_name,
            'patient_phone' => $booking->patient->phone,
            'cancelled_at' => $this->iso($booking->cancelled_at),
            'cancellation' => $booking->cancelled_by === null ? null : [
                'by' => $booking->cancelled_by->value,
                'reason' => (string) $booking->cancellation_reason,
            ],
        ];
    }
}
