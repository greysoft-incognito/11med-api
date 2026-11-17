<?php

namespace App\Services\Notifications\Push;

use App\Enums\NotificationCategory;

/**
 * What one push says and carries: the title and body, and `zPushPayload`, ids only.
 */
final readonly class PushMessage
{
    public function __construct(
        public NotificationCategory $category,
        public string $notificationId,
        public ?string $targetId,
        public string $title,
        public string $body,
    ) {}

    /**
     * @return array{kind: string, category: string, notification_id: string, target_id: ?string}
     */
    public function payload(): array
    {
        return [
            'kind' => 'push',
            'category' => $this->category->value,
            'notification_id' => $this->notificationId,
            'target_id' => $this->targetId,
        ];
    }
}
