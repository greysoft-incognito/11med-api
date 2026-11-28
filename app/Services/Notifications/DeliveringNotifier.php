<?php

namespace App\Services\Notifications;

use App\Enums\AdminPermission;
use App\Enums\AuthClient;
use App\Enums\NotificationCategory;
use App\Jobs\Notifications\DeliverPush;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Notifications\Push\PushMessage;
use App\Services\Staff\PermissionHolders;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Writes the history row when the user keeps in-app history for the category, then queues the
 * push when they allow it and the app shows them the category. A repeated dedupe key does
 * neither again. A fan-out walks its audience in batches.
 */
class DeliveringNotifier implements Notifier
{
    private const BATCH = 200;

    public function __construct(
        private readonly NotificationPreferences $preferences,
        private readonly NotificationHistory $history,
        private readonly PermissionHolders $holders,
    ) {}

    public function send(User $to, NotificationCategory $category, string $title, string $body, array $data = []): ?string
    {
        $preference = $this->preferences->for($to, $category);
        $targetId = $data['target_id'] ?? null;
        $notificationId = null;

        if ($preference->in_app) {
            [$notification, $fresh] = $this->history->record($to, $category, $title, $body, $targetId, $data['dedupe_key'] ?? null);

            if (! $fresh) {
                return $notification->uuid;
            }

            $notificationId = $notification->uuid;
        }

        if (($data['push'] ?? true) && $preference->push && in_array($category, NotificationCategory::for($to, AuthClient::App), true)) {
            $message = new PushMessage($category, $notificationId ?? (string) Str::uuid7(), $targetId, $title, $body);

            DeliverPush::dispatch($to->id, $message)->afterCommit();
        }

        return $notificationId;
    }

    public function toOrganisation(Organisation $organisation, NotificationCategory $category, string $title, string $body, array $data = []): void
    {
        $audience = User::query()->where('organisation_id', $organisation->id);

        $this->fanOut($audience, $category, $title, $body, $data);
    }

    public function toPermission(AdminPermission $permission, NotificationCategory $category, string $title, string $body, array $data = []): void
    {
        $this->fanOut($this->holders->holders($permission), $category, $title, $body, $data);
    }

    /**
     * @param  Builder<User>  $audience
     * @param  array{target_id?: ?string, dedupe_key?: ?string}  $data
     */
    private function fanOut(Builder $audience, NotificationCategory $category, string $title, string $body, array $data): void
    {
        $audience
            ->with('accountKinds')
            ->chunkById(self::BATCH, function (Collection $users) use ($category, $title, $body, $data) {
                $users->each(fn(User $user) => $this->send($user, $category, $title, $body, $data));
            });
    }
}
