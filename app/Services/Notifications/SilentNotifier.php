<?php

namespace App\Services\Notifications;

use App\Enums\AdminPermission;
use App\Enums\NotificationCategory;
use App\Models\Organisation;
use App\Models\User;

/**
 * Sends and records nothing, for a context that must not notify anyone.
 */
class SilentNotifier implements Notifier
{
    public function send(User $to, NotificationCategory $category, string $title, string $body, array $data = []): ?string
    {
        return null;
    }

    public function toOrganisation(Organisation $organisation, NotificationCategory $category, string $title, string $body, array $data = []): void {}

    public function toPermission(AdminPermission $permission, NotificationCategory $category, string $title, string $body, array $data = []): void {}
}
