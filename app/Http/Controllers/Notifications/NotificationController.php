<?php

namespace App\Http\Controllers\Notifications;

use App\Enums\AuthClient;
use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Notifications\UpdatePreferencesRequest;
use App\Http\Resources\EmptyResource;
use App\Http\Resources\Notifications\NotificationCollection;
use App\Http\Resources\Notifications\NotificationPreferencesResource;
use App\Services\Notifications\NotificationHistory;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationHistory $history,
        private readonly NotificationPreferences $preferences,
    ) {}

    public function index(Request $request, string $userId): NotificationCollection
    {
        $list = $this->history->list($request->user(), $userId, AuthClient::App, ListQuery::from($request));

        return new NotificationCollection($list);
    }

    public function read(Request $request, string $userId, string $notificationId): EmptyResource
    {
        $this->history->markRead($request->user(), $userId, $notificationId);

        return new EmptyResource;
    }

    public function readAll(Request $request, string $userId): EmptyResource
    {
        $this->history->markAllRead($request->user(), $userId, AuthClient::App);

        return new EmptyResource;
    }

    public function preferences(Request $request, string $userId): NotificationPreferencesResource
    {
        $preferences = $this->preferences->of($request->user(), $userId, AuthClient::App);

        return new NotificationPreferencesResource($preferences);
    }

    public function updatePreferences(UpdatePreferencesRequest $request, string $userId): NotificationPreferencesResource
    {
        $preferences = $this->preferences->save($request->user(), $userId, AuthClient::App, $request->validated('preferences'));

        return new NotificationPreferencesResource($preferences);
    }
}
