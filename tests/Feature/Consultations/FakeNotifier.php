<?php

namespace Tests\Feature\Consultations;

use App\Enums\NotificationCategory;
use App\Models\User;
use App\Services\Notifications\Notifier;

/**
 * Records every notification instead of writing and pushing it.
 */
class FakeNotifier implements Notifier
{
    /** @var list<array{to: int, category: NotificationCategory, title: string, body: string, data: array<string, mixed>}> */
    public array $sent = [];

    public function send(User $to, NotificationCategory $category, string $title, string $body, array $data = []): ?string
    {
        $this->sent[] = ['to' => $to->id, 'category' => $category, 'title' => $title, 'body' => $body, 'data' => $data];

        return null;
    }

    /**
     * @return list<array{to: int, category: NotificationCategory, title: string, body: string, data: array<string, mixed>}>
     */
    public function to(User $user): array
    {
        return array_values(array_filter($this->sent, fn(array $sent) => $sent['to'] === $user->id));
    }
}
