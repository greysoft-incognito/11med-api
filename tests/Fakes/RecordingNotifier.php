<?php

namespace Tests\Fakes;

use App\Enums\NotificationCategory;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Support\Str;

/**
 * Keeps every notification sent, for assertions.
 */
class RecordingNotifier implements Notifier
{
    /**
     * @var list<array{to: int, category: NotificationCategory, title: string, body: string, data: array<string, mixed>}>
     */
    public array $sent = [];

    public function send(User $to, NotificationCategory $category, string $title, string $body, array $data = []): ?string
    {
        $this->sent[] = ['to' => $to->id, 'category' => $category, 'title' => $title, 'body' => $body, 'data' => $data];

        return (string) Str::uuid7();
    }
}
