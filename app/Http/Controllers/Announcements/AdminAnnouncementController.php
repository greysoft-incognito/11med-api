<?php

namespace App\Http\Controllers\Announcements;

use App\Enums\AnnouncementAudience;
use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Announcements\CreateAnnouncementRequest;
use App\Http\Requests\Announcements\PreviewAnnouncementRequest;
use App\Http\Resources\Announcements\AnnouncementCollection;
use App\Http\Resources\Announcements\AnnouncementReachResource;
use App\Http\Resources\Announcements\AnnouncementResource;
use App\Services\Announcements\Announcements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAnnouncementController extends Controller
{
    public function __construct(
        private readonly Announcements $announcements,
    ) {}

    public function index(Request $request): AnnouncementCollection
    {
        $page = $this->announcements->list(ListQuery::from($request));

        return new AnnouncementCollection($page);
    }

    public function preview(PreviewAnnouncementRequest $request): AnnouncementReachResource
    {
        $audience = AnnouncementAudience::from($request->validated('audience'));
        $reach = ['audience' => $audience->value, 'recipients' => $this->announcements->reach($audience)];

        return new AnnouncementReachResource($reach);
    }

    public function store(CreateAnnouncementRequest $request): JsonResponse
    {
        $announcement = $this->announcements->create($request->user(), $request->validated());

        return (new AnnouncementResource($announcement))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }

    public function cancel(Request $request, string $id): AnnouncementResource
    {
        $announcement = $this->announcements->cancel($request->user(), $id);

        return new AnnouncementResource($announcement);
    }
}
