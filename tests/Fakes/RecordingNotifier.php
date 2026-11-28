<?php

namespace Tests\Fakes;

use App\Enums\AdminPermission;
use App\Enums\NotificationCategory;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Support\Str;

/**
 * Keeps every notification sent, for assertions.
 */
class RecordingNotifier implements Notifier
{
    /**
     * @var list<array{to: ?int, audience?: string, category: NotificationCategory, title: string, body: string, data: array<string, mixed>}>
     */
    public array $sent = [];

    public function send(User $to, NotificationCategory $category, string $title, string $body, array $data = []): ?string
    {
        $this->sent[] = ['to' => $to->id, 'category' => $category, 'title' => $title, 'body' => $body, 'data' => $data];

        return (string) Str::uuid7();
    }

    public function toOrganisation(Organisation $organisation, NotificationCategory $category, string $title, string $body, array $data = []): void
    {
        $this->sent[] = ['to' => null, 'audience' => "organisation:{$organisation->id}", 'category' => $category, 'title' => $title, 'body' => $body, 'data' => $data];
    }

    public function toPermission(AdminPermission $permission, NotificationCategory $category, string $title, string $body, array $data = []): void
    {
        $this->sent[] = ['to' => null, 'audience' => "permission:{$permission->value}", 'category' => $category, 'title' => $title, 'body' => $body, 'data' => $data];
    }
}
