<?php

namespace App\Http\Requests\Operations;

use App\Enums\OrderStatus;

class ListOrdersRequest extends OversightListRequest
{
    protected function statuses(): array
    {
        return array_column(OrderStatus::cases(), 'value');
    }
}
