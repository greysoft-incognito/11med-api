<?php

namespace App\Services\Notifications;

use App\Enums\AuthClient;
use App\Enums\NotificationCategory;
use App\Exceptions\ApiException;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Per-category delivery choices (app handover §8.3). Every category is on until the user saves
 * otherwise, SMS excepted, and a category added after the user saved still appears, defaulted.
 * Each client lists only the categories the account is told about there (`NotificationCategory::for`).
 */
class NotificationPreferences
{
    /**
     * Every category, in the contract's order, saved or defaulted.
     *
     * @return Collection<int, NotificationPreference>
     */
    public function of(User $caller, string $userId, AuthClient $client): Collection
    {
        $this->ensureSelf($caller, $userId);

        return $this->all($caller, $client);
    }

    /**
     * @param  list<array{category: string, push: bool, sms: bool, in_app: bool}>  $preferences
     * @return Collection<int, NotificationPreference>
     */
    public function save(User $caller, string $userId, AuthClient $client, array $preferences): Collection
    {
        $this->ensureSelf($caller, $userId);

        DB::transaction(function () use ($caller, $preferences) {
            foreach ($preferences as $preference) {
                NotificationPreference::query()->updateOrCreate(
                    ['user_id' => $caller->id, 'category' => $preference['category']],
                    [
                        'push' => $preference['push'],
                        'sms' => $preference['sms'],
                        'in_app' => $preference['in_app'],
                    ],
                );
            }
        });

        return $this->all($caller, $client);
    }

    public function for(User $user, NotificationCategory $category): NotificationPreference
    {
        $saved = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('category', $category->value)
            ->first();

        return $saved ?? $this->default($user, $category);
    }

    /**
     * @return Collection<int, NotificationPreference>
     */
    private function all(User $user, AuthClient $client): Collection
    {
        $saved = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy(fn(NotificationPreference $preference) => $preference->category->value);

        return collect(NotificationCategory::for($user, $client))
            ->map(fn(NotificationCategory $category) => $saved->get($category->value) ?? $this->default($user, $category));
    }

    private function default(User $user, NotificationCategory $category): NotificationPreference
    {
        return new NotificationPreference([
            'user_id' => $user->id,
            'category' => $category,
            'push' => true,
            'sms' => false,
            'in_app' => true,
        ]);
    }

    private function ensureSelf(User $caller, string $userId): void
    {
        if ($caller->uuid !== $userId) {
            throw ApiException::notFound();
        }
    }
}
