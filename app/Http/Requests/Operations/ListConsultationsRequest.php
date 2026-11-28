<?php

namespace App\Http\Requests\Operations;

use App\Enums\ConsultationStatus;

class ListConsultationsRequest extends OversightListRequest
{
    protected function statuses(): array
    {
        return array_column(ConsultationStatus::cases(), 'value');
    }
}
