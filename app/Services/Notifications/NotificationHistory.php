<?php

namespace App\Services\Notifications;

use App\Enums\NotificationCategory;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A user's notification history (app handover §8.2): the only writer of `app_notifications`.
 */
class NotificationHistory
{
    public function list(User $caller, string $userId, ListQuery $list): NotificationList
    {
        $this->ensureSelf($caller, $userId);

        $mine = AppNotification::query()->where('user_id', $caller->id);
        $unread = $this->unreadCount($caller);

        $page = $list->paginate(
            $mine
                ->orderByDesc('created_at')
                ->orderByDesc('id')
        );

        return new NotificationList($page, $unread);
    }

    public function unreadCount(User $user): int
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * The first read is the one kept; reading again changes nothing.
     */
    public function markRead(User $caller, string $userId, string $notificationId): void
    {
        $this->ensureSelf($caller, $userId);

        $notification = AppNotification::query()
            ->where('user_id', $caller->id)
            ->where('uuid', $notificationId)
            ->first() ?? throw ApiException::notFound();

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }
    }

    public function markAllRead(User $caller, string $userId): void
    {
        $this->ensureSelf($caller, $userId);

        AppNotification::query()
            ->where('user_id', $caller->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Writes the row. With a dedupe key already used for this user, nothing is written and the
     * earlier row comes back as not fresh.
     *
     * @return array{0: AppNotification, 1: bool} the row, and whether this call wrote it
     */
    public function record(User $to, NotificationCategory $category, string $title, string $body, ?string $targetId, ?string $dedupeKey): array
    {
        if ($dedupeKey === null) {
            $notification = AppNotification::create([
                'user_id' => $to->id,
                'category' => $category,
                'title' => $title,
                'body' => $body,
                'target_id' => $targetId,
            ]);

            return [$notification, true];
        }

        $now = now();

        $written = DB::table('app_notifications')->insertOrIgnore([
            'uuid' => (string) Str::uuid7(),
            'user_id' => $to->id,
            'category' => $category->value,
            'title' => $title,
            'body' => $body,
            'target_id' => $targetId,
            'dedupe_key' => $dedupeKey,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $notification = AppNotification::query()
            ->where('user_id', $to->id)
            ->where('dedupe_key', $dedupeKey)
            ->sole();

        return [$notification, $written === 1];
    }

    private function ensureSelf(User $caller, string $userId): void
    {
        if ($caller->uuid !== $userId) {
            throw ApiException::notFound();
        }
    }
}
