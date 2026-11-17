<?php

namespace App\Http\Controllers\Desk;

use App\Http\Controllers\Controller;
use App\Http\Resources\Desk\AdminSummaryResource;
use App\Services\Desk\DeskSummary;

/**
 * The admin portal's home: what waits on the desk.
 */
class AdminSummaryController extends Controller
{
    public function __construct(
        private readonly DeskSummary $summary,
    ) {}

    public function show(): AdminSummaryResource
    {
        $summary = $this->summary->read();

        return new AdminSummaryResource($summary);
    }
}
