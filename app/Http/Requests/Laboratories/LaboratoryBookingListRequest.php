<?php

namespace App\Http\Requests\Laboratories;

use App\Enums\LabBookingStatus;
use App\Http\Requests\ApiRequest;
use App\Services\Laboratories\LaboratoryBookings;
use Illuminate\Validation\Rule;

/**
 * The laboratory's booking list filters: a stage or `open`, and `q`.
 */
class LaboratoryBookingListRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::in([...array_column(LabBookingStatus::cases(), 'value'), LaboratoryBookings::OPEN])],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
