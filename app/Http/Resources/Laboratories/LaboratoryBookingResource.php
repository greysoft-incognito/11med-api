<?php

namespace App\Http\Resources\Laboratories;

use App\Http\Resources\ApiResource;
use App\Models\LabBooking;
use Illuminate\Http\Request;

/**
 * Portal `zLaboratoryBooking`: the booking, who booked it and how to reach them, and the doctor's
 * note when a request lies behind it.
 *
 * @property LabBooking $resource
 */
class LaboratoryBookingResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $booking = $this->resource;

        return [
            ...LabBookingResource::shared($booking),
            'patient_name' => $booking->patient->full_name,
            'patient_phone' => $booking->patient->phone,
            'doctor_name' => $booking->request?->doctor_name,
            'request_notes' => $booking->request?->notes,
            'results_name' => $booking->results_name,
            'cancelled_at' => $this->iso($booking->cancelled_at),
            'cancellation' => $booking->cancelled_by === null ? null : [
                'by' => $booking->cancelled_by->value,
                'reason' => (string) $booking->cancellation_reason,
            ],
        ];
    }
}
