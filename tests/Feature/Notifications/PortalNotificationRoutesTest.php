<?php

namespace Tests\Feature\Notifications;

use App\Enums\AccountKind;
use App\Enums\NotificationCategory;
use App\Jobs\Notifications\DeliverPush;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PortalNotificationRoutesTest extends TestCase
{
    public function test_a_portal_account_pages_its_history_with_the_unread_count(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        foreach (['First', 'Second', 'Third'] as $title) {
            $this->notify($admin, $title);
            $this->travel(1)->minutes();
        }

        $this->notify($other, 'Not mine');

        $first = $this->actingAs($admin)
            ->getJson('api/v1/portal/notifications?limit=2')
            ->assertOk()
            ->assertJsonPath('data.items.0.title', 'Third')
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.unread_count', 3)
            ->assertJsonPath('data.has_more', true)
            ->assertJsonStructure(['data' => ['items' => [['id', 'category', 'title', 'body', 'target_id', 'read_at', 'created_at']], 'next_cursor']]);

        $this->actingAs($admin)
            ->getJson('api/v1/portal/notifications?limit=2&cursor=' . $first->json('data.next_cursor'))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'First');

        $this->actingAs($admin)
            ->getJson('api/v1/portal/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data', ['unread_count' => 3]);
    }

    public function test_reading_one_and_reading_all(): void
    {
        $pharmacy = $this->account(AccountKind::Pharmacy);
        $one = $this->notify($pharmacy, 'One');
        $this->notify($pharmacy, 'Two');

        $this->actingAs($pharmacy)
            ->patchJson("api/v1/portal/notifications/{$one}/read")
            ->assertOk();

        $this->actingAs($pharmacy)
            ->getJson('api/v1/portal/notifications/unread-count')
            ->assertJsonPath('data.unread_count', 1);

        $this->actingAs($pharmacy)
            ->patchJson('api/v1/portal/notifications/read-all')
            ->assertOk();

        $this->actingAs($pharmacy)
            ->getJson('api/v1/portal/notifications/unread-count')
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_another_accounts_notification_is_not_found(): void
    {
        $owner = $this->account(AccountKind::Laboratory);
        $id = $this->notify($owner, 'Mine');

        $this->actingAs($this->account(AccountKind::Laboratory))
            ->patchJson("api/v1/portal/notifications/{$id}/read")
            ->assertNotFound();
    }

    public function test_each_portal_role_is_offered_its_own_categories(): void
    {
        $expected = [
            'pharmacy' => ['pharmacy_order', 'account', 'announcement'],
            'laboratory' => ['lab_booking', 'account', 'announcement'],
            'admin' => ['doctor_review', 'partner_application', 'payment_alert', 'account', 'announcement'],
        ];

        foreach ($expected as $role => $categories) {
            $account = $role === 'admin' ? $this->admin() : $this->account(AccountKind::from($role));

            $response = $this->actingAs($account)
                ->getJson('api/v1/portal/notifications/preferences')
                ->assertOk();

            $this->assertSame($categories, array_column($response->json('data.preferences'), 'category'), $role);
        }
    }

    public function test_preferences_save_and_refuse_another_roles_category(): void
    {
        $lab = $this->account(AccountKind::Laboratory);

        $this->actingAs($lab)
            ->patchJson('api/v1/portal/notifications/preferences', ['preferences' => [
                ['category' => 'lab_booking', 'push' => true, 'sms' => false, 'in_app' => false],
            ]])
            ->assertOk()
            ->assertJsonPath('data.preferences.0', ['category' => 'lab_booking', 'push' => true, 'sms' => false, 'in_app' => false]);

        $this->actingAs($lab)
            ->patchJson('api/v1/portal/notifications/preferences', ['preferences' => [
                ['category' => 'pharmacy_order', 'push' => true, 'sms' => false, 'in_app' => true],
            ]])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.field', 'preferences.0.category');

        $this->assertNull(app(Notifier::class)->send($lab, NotificationCategory::LabBooking, 'Muted', 'Not kept.'));
    }

    public function test_a_portal_account_gets_the_row_and_never_a_push(): void
    {
        Queue::fake();

        $pharmacy = $this->account(AccountKind::Pharmacy);

        $this->assertNotNull(app(Notifier::class)->send($pharmacy, NotificationCategory::PharmacyOrder, 'New order', 'Start it.'));

        Queue::assertNotPushed(DeliverPush::class);
    }

    public function test_app_accounts_and_guests_are_refused(): void
    {
        $patient = User::factory()
            ->app(AccountKind::Patient)
            ->create();

        $this->actingAs($patient)
            ->getJson('api/v1/portal/notifications')
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->getJson('api/v1/portal/notifications/unread-count')
            ->assertUnauthorized();
    }

    public function test_a_doctor_is_offered_only_the_doctors_categories(): void
    {
        $doctor = User::factory()
            ->app(AccountKind::Doctor)
            ->create();

        $response = $this->actingAs($doctor)
            ->getJson("api/v1/notifications/{$doctor->uuid}/preferences")
            ->assertOk();

        $this->assertSame(['consultation', 'account', 'announcement'], array_column($response->json('data.preferences'), 'category'));
    }

    private function account(AccountKind $role): User
    {
        return User::factory()
            ->portal($role)
            ->create();
    }

    private function notify(User $user, string $title): string
    {
        return app(Notifier::class)->send($user, NotificationCategory::Account, $title, 'Body.');
    }
}
