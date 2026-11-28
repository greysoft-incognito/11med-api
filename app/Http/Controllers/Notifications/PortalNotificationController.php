<?php

namespace App\Http\Controllers\Notifications;

use App\Enums\AuthClient;
use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Notifications\UpdatePreferencesRequest;
use App\Http\Resources\EmptyResource;
use App\Http\Resources\Notifications\NotificationCollection;
use App\Http\Resources\Notifications\NotificationPreferencesResource;
use App\Http\Resources\Notifications\UnreadCountResource;
use App\Services\Notifications\NotificationHistory;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Http\Request;

/**
 * A portal account's own notifications: the app's history and preferences, addressed by the
 * signed-in account rather than an id in the path.
 */
class PortalNotificationController extends Controller
{
    public function __construct(
        private readonly NotificationHistory $history,
        private readonly NotificationPreferences $preferences,
    ) {}

    public function index(Request $request): NotificationCollection
    {
        $user = $request->user();
        $list = $this->history->list($user, $user->uuid, AuthClient::Portal, ListQuery::from($request));

        return new NotificationCollection($list);
    }

    public function unreadCount(Request $request): UnreadCountResource
    {
        $count = $this->history->unreadCount($request->user(), AuthClient::Portal);

        return new UnreadCountResource($count);
    }

    public function read(Request $request, string $notificationId): EmptyResource
    {
        $user = $request->user();

        $this->history->markRead($user, $user->uuid, $notificationId);

        return new EmptyResource;
    }

    public function readAll(Request $request): EmptyResource
    {
        $user = $request->user();

        $this->history->markAllRead($user, $user->uuid, AuthClient::Portal);

        return new EmptyResource;
    }

    public function preferences(Request $request): NotificationPreferencesResource
    {
        $user = $request->user();
        $preferences = $this->preferences->of($user, $user->uuid, AuthClient::Portal);

        return new NotificationPreferencesResource($preferences);
    }

    public function updatePreferences(UpdatePreferencesRequest $request): NotificationPreferencesResource
    {
        $user = $request->user();
        $preferences = $this->preferences->save($user, $user->uuid, AuthClient::Portal, $request->validated('preferences'));

        return new NotificationPreferencesResource($preferences);
    }
}
