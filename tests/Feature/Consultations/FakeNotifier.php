<?php

namespace Tests\Feature\Consultations;

use App\Enums\AdminPermission;
use App\Enums\NotificationCategory;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Notifications\Notifier;

/**
 * Records every notification instead of writing and pushing it.
 */
class FakeNotifier implements Notifier
{
    /** @var list<array{to: ?int, audience?: string, category: NotificationCategory, title: string, body: string, data: array<string, mixed>}> */
    public array $sent = [];

    public function send(User $to, NotificationCategory $category, string $title, string $body, array $data = []): ?string
    {
        $this->sent[] = ['to' => $to->id, 'category' => $category, 'title' => $title, 'body' => $body, 'data' => $data];

        return null;
    }

    public function toOrganisation(Organisation $organisation, NotificationCategory $category, string $title, string $body, array $data = []): void
    {
        $this->sent[] = ['to' => null, 'audience' => "organisation:{$organisation->id}", 'category' => $category, 'title' => $title, 'body' => $body, 'data' => $data];
    }

    public function toPermission(AdminPermission $permission, NotificationCategory $category, string $title, string $body, array $data = []): void
    {
        $this->sent[] = ['to' => null, 'audience' => "permission:{$permission->value}", 'category' => $category, 'title' => $title, 'body' => $body, 'data' => $data];
    }

    /**
     * @return list<array{to: ?int, audience?: string, category: NotificationCategory, title: string, body: string, data: array<string, mixed>}>
     */
    public function to(User $user): array
    {
        return array_values(array_filter($this->sent, fn(array $sent) => $sent['to'] === $user->id));
    }

    /**
     * Drops what was sent to a user so far, such as the approval a test doctor is set up with.
     */
    public function forget(User $user): void
    {
        $this->sent = array_values(array_filter($this->sent, fn(array $sent) => $sent['to'] !== $user->id));
    }
}
