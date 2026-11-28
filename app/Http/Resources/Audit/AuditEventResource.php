<?php

namespace App\Http\Resources\Audit;

use App\Http\Resources\ApiResource;
use App\Models\AuditEvent;
use Illuminate\Http\Request;

/**
 * `zAuditEvent`.
 *
 * @property AuditEvent $resource
 */
class AuditEventResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $event = $this->resource;

        return [
            'id' => $event->uuid,
            'action' => $event->action,
            'actor_id' => $event->actor?->uuid,
            'actor_name' => $event->actor_name,
            'subject_type' => $event->subject_type,
            'subject_id' => $event->subject_id,
            'meta' => (object) $event->meta,
            'created_at' => $this->iso($event->created_at),
        ];
    }
}
