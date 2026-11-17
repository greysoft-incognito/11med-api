<?php

namespace App\Services\Notifications;

use App\Enums\NotificationCategory;
use App\Models\User;
use Illuminate\Container\Attributes\Bind;

/**
 * Tells a user something happened: writes their notification history row and pushes it to their
 * devices as their preferences allow (app handover §8). `DeliveringNotifier` implements it.
 */
#[Bind(DeliveringNotifier::class)]
interface Notifier
{
    /**
     * The title says what happened, never what is wrong. `$data` takes `target_id` (the public id a
     * tap lands on), `dedupe_key` (sent once per key) and `push` (true by default). Returns the
     * history row's public id, or null when none was written.
     *
     * @param  array{target_id?: ?string, dedupe_key?: ?string, push?: bool}  $data
     */
    public function send(User $to, NotificationCategory $category, string $title, string $body, array $data = []): ?string;
}
