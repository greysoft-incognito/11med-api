<?php

namespace Tests\Feature\Notifications;

use App\Enums\AccountKind;
use App\Enums\NotificationCategory;
use App\Models\AppNotification;
use App\Models\User;
use App\Services\Notifications\NotificationHistory;
use Tests\TestCase;

class NotificationRoutesTest extends TestCase
{
    public function test_the_history_is_paged_newest_first_with_the_unread_count(): void
    {
        $user = $this->patient();

        foreach (['First', 'Second', 'Third'] as $title) {
            $this->notify($user, $title);
            $this->travel(1)->minutes();
        }

        $first = $this->actingAs($user)
            ->getJson("api/v1/notifications/{$user->uuid}?limit=2")
            ->assertOk()
            ->assertJsonPath('data.items.0.title', 'Third')
            ->assertJsonPath('data.items.1.title', 'Second')
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.unread_count', 3)
            ->assertJsonPath('data.has_more', true)
            ->assertJsonStructure(['data' => ['items' => [['id', 'category', 'title', 'body', 'target_id', 'read_at', 'created_at']], 'next_cursor']]);

        $this->actingAs($user)
            ->getJson("api/v1/notifications/{$user->uuid}?limit=2&cursor=" . $first->json('data.next_cursor'))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'First')
            ->assertJsonPath('data.has_more', false)
            ->assertJsonPath('data.next_cursor', null);
    }

    public function test_an_empty_history(): void
    {
        $user = $this->patient();

        $this->actingAs($user)
            ->getJson("api/v1/notifications/{$user->uuid}")
            ->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.unread_count', 0)
            ->assertJsonPath('data.has_more', false);
    }

    public function test_a_bad_cursor_is_refused(): void
    {
        $user = $this->patient();

        $this->actingAs($user)
            ->getJson("api/v1/notifications/{$user->uuid}?cursor=nonsense")
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.field', 'cursor');
    }

    public function test_reading_one_keeps_the_first_read_time(): void
    {
        $user = $this->patient();
        $id = $this->notify($user, 'Accepted');

        $this->actingAs($user)
            ->patchJson("api/v1/notifications/{$user->uuid}/read/{$id}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $firstRead = AppNotification::sole()->read_at;
        $this->travel(5)->minutes();

        $this->actingAs($user)
            ->patchJson("api/v1/notifications/{$user->uuid}/read/{$id}")
            ->assertOk();

        $this->assertEquals($firstRead, AppNotification::sole()->read_at);

        $this->actingAs($user)
            ->getJson("api/v1/notifications/{$user->uuid}")
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_reading_someone_elses_notification_is_not_found(): void
    {
        $owner = $this->patient();
        $stranger = $this->patient();
        $id = $this->notify($owner, 'Accepted');

        $this->actingAs($stranger)
            ->patchJson("api/v1/notifications/{$stranger->uuid}/read/{$id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $this->assertNull(AppNotification::sole()->read_at);
    }

    public function test_read_all_marks_only_the_callers_rows(): void
    {
        $user = $this->patient();
        $other = $this->patient();
        $this->notify($user, 'One');
        $this->notify($user, 'Two');
        $this->notify($other, 'Theirs');

        $this->actingAs($user)
            ->patchJson("api/v1/notifications/{$user->uuid}/read-all")
            ->assertOk();

        $this->assertSame(0, AppNotification::where('user_id', $user->id)->whereNull('read_at')->count());
        $this->assertSame(1, AppNotification::where('user_id', $other->id)->whereNull('read_at')->count());
    }

    public function test_another_accounts_id_is_not_found_on_every_route(): void
    {
        $user = $this->patient();
        $other = $this->patient();

        $this->actingAs($user)->getJson("api/v1/notifications/{$other->uuid}")->assertNotFound();
        $this->actingAs($user)->patchJson("api/v1/notifications/{$other->uuid}/read-all")->assertNotFound();
        $this->actingAs($user)->getJson("api/v1/notifications/{$other->uuid}/preferences")->assertNotFound();
        $this->actingAs($user)->patchJson("api/v1/notifications/{$other->uuid}/preferences", ['preferences' => []])->assertNotFound();
    }

    public function test_a_doctor_has_notifications_too(): void
    {
        $doctor = User::factory()
            ->app(AccountKind::Doctor)
            ->create();

        $this->actingAs($doctor)
            ->getJson("api/v1/notifications/{$doctor->uuid}")
            ->assertOk();
    }

    public function test_portal_accounts_and_guests_are_refused(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $this->actingAs($admin)
            ->getJson("api/v1/notifications/{$admin->uuid}")
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->getJson("api/v1/notifications/{$admin->uuid}")
            ->assertUnauthorized();
    }

    public function test_preferences_default_on_except_sms(): void
    {
        $user = $this->patient();

        $response = $this->actingAs($user)
            ->getJson("api/v1/notifications/{$user->uuid}/preferences")
            ->assertOk()
            ->assertJsonCount(count(NotificationCategory::forKind(AccountKind::Patient)), 'data.preferences')
            ->assertJsonPath('data.preferences.0', ['category' => 'medication_reminder', 'push' => true, 'sms' => false, 'in_app' => true]);

        $this->assertSame(
            array_map(fn(NotificationCategory $category) => $category->value, NotificationCategory::forKind(AccountKind::Patient)),
            array_column($response->json('data.preferences'), 'category'),
        );
    }

    public function test_saved_preferences_persist_and_unsaved_categories_stay_defaulted(): void
    {
        $user = $this->patient();
        $body = ['preferences' => [
            ['category' => 'order', 'push' => false, 'sms' => false, 'in_app' => true],
            ['category' => 'missed_dose', 'push' => true, 'sms' => true, 'in_app' => false],
        ]];

        $this->actingAs($user)
            ->patchJson("api/v1/notifications/{$user->uuid}/preferences", $body)
            ->assertOk()
            ->assertJsonCount(count(NotificationCategory::forKind(AccountKind::Patient)), 'data.preferences');

        $this->actingAs($user)
            ->patchJson("api/v1/notifications/{$user->uuid}/preferences", ['preferences' => [
                ['category' => 'order', 'push' => true, 'sms' => false, 'in_app' => true],
            ]])
            ->assertOk();

        $preferences = collect(
            $this->actingAs($user)
                ->getJson("api/v1/notifications/{$user->uuid}/preferences")
                ->json('data.preferences')
        )->keyBy('category');

        $this->assertTrue($preferences['order']['push']);
        $this->assertFalse($preferences['missed_dose']['in_app']);
        $this->assertTrue($preferences['missed_dose']['sms']);
        $this->assertTrue($preferences['consultation']['push']);
    }

    public function test_invalid_preferences_are_refused_by_field(): void
    {
        $user = $this->patient();
        $valid = ['category' => 'order', 'push' => true, 'sms' => false, 'in_app' => true];

        $cases = [
            ['preferences', []],
            ['preferences.0.category', ['preferences' => [['category' => 'gossip'] + $valid]]],
            ['preferences.0.push', ['preferences' => [['push' => 'yes'] + $valid]]],
            ['preferences.0.category', ['preferences' => [$valid, $valid]]],
        ];

        foreach ($cases as [$field, $body]) {
            $this->actingAs($user)
                ->patchJson("api/v1/notifications/{$user->uuid}/preferences", $body)
                ->assertStatus(400)
                ->assertJsonPath('error.code', 'VALIDATION_FAILED')
                ->assertJsonPath('error.field', $field);
        }
    }

    private function patient(): User
    {
        return User::factory()
            ->app()
            ->create();
    }

    private function notify(User $user, string $title): string
    {
        [$notification] = app(NotificationHistory::class)->record(
            $user,
            NotificationCategory::Consultation,
            $title,
            'Open the app for details.',
            '0b7f0f0e-5d6a-4c43-9d1e-2f4a8f0c9a11',
            null,
        );

        return $notification->uuid;
    }
}
