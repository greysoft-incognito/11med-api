<?php

namespace App\Http\Resources\Support;

use App\Http\Resources\ApiCollection;

/**
 * `zPage(zSupportTicket)`.
 */
class SupportTicketCollection extends ApiCollection
{
    public $collects = SupportTicketResource::class;
}
