<?php

namespace App\Http\Requests\Operations;

use App\Enums\LabBookingStatus;

class ListLabBookingsRequest extends OversightListRequest
{
    protected function statuses(): array
    {
        return array_column(LabBookingStatus::cases(), 'value');
    }
}
