<?php

namespace App\Http\Resources\Announcements;

use App\Http\Resources\ApiCollection;

/**
 * The team's announcements, newest first, paged.
 */
class AnnouncementCollection extends ApiCollection
{
    public $collects = AnnouncementResource::class;
}
