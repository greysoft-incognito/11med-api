<?php

namespace App\Http\Resources\Audit;

use App\Http\Resources\ApiCollection;

/**
 * `zPage(zAuditEvent)`.
 */
class AuditEventCollection extends ApiCollection
{
    public $collects = AuditEventResource::class;
}
