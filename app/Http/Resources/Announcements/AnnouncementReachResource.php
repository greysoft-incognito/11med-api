<?php

namespace App\Http\Resources\Announcements;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * Portal `zAnnouncementPreview`: the accounts an audience reaches now.
 *
 * @property array{audience: string, recipients: int} $resource
 */
class AnnouncementReachResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
