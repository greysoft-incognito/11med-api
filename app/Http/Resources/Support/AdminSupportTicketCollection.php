<?php

namespace App\Http\Resources\Support;

use App\Http\Resources\ApiCollection;

/**
 * `zPage(zAdminSupportTicket)`.
 */
class AdminSupportTicketCollection extends ApiCollection
{
    public $collects = AdminSupportTicketResource::class;
}
