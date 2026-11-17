<?php

namespace App\Http\Resources\Readings;

use App\Http\Paging\Page;
use App\Http\Resources\ApiCollection;
use Illuminate\Http\Request;

/**
 * `zReadings`, paged (FE-003): the window the account was given, and the readings in it.
 */
class ReadingCollection extends ApiCollection
{
    public $collects = ReadingResource::class;

    public function __construct(Page $page, private readonly int $windowDays)
    {
        parent::__construct($page);
    }

    public function toArray(Request $request): array
    {
        return ['window_days' => $this->windowDays, ...parent::toArray($request)];
    }
}
