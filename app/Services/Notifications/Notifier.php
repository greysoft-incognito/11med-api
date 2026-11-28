<?php

namespace App\Services\Notifications;

use App\Enums\AdminPermission;
use App\Enums\NotificationCategory;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Container\Attributes\Bind;

/**
 * Tells someone something happened: writes their notification history row and pushes it to their
 * devices as their preferences allow (app handover §8). App and portal accounts alike; portal
 * accounts have no devices, so they get the row only. `DeliveringNotifier` implements it.
 */
#[Bind(DeliveringNotifier::class)]
interface Notifier
{
    /**
     * The title says what happened, never what is wrong. `$data` takes `target_id` (the public id a
     * tap lands on), `dedupe_key` (sent once per key and user) and `push` (true by default). Returns
     * the history row's public id, or null when none was written.
     *
     * @param  array{target_id?: ?string, dedupe_key?: ?string, push?: bool}  $data
     */
    public function send(User $to, NotificationCategory $category, string $title, string $body, array $data = []): ?string;

    /**
     * Every portal account of a pharmacy or laboratory, in batches.
     *
     * @param  array{target_id?: ?string, dedupe_key?: ?string}  $data
     */
    public function toOrganisation(Organisation $organisation, NotificationCategory $category, string $title, string $body, array $data = []): void;

    /**
     * Every admin who holds the permission, directly or through a role, in batches.
     *
     * @param  array{target_id?: ?string, dedupe_key?: ?string}  $data
     */
    public function toPermission(AdminPermission $permission, NotificationCategory $category, string $title, string $body, array $data = []): void;
}
