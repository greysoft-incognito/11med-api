<?php

namespace Tests\Feature;

use App\Events\Verified;
use App\Helpers\Url;
use App\Models\User;
use App\Notifications\AccountVerified;
use App\Notifications\SendCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\Notifications\TestNotifications;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     */
    public function test_can_send_verification_code(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $user->sendEmailVerificationNotification();

        Notification::assertSentTo(
            $user,
            function (SendCode $notification, array $channels) use ($user) {
                return $notification->code === $user->email_verify_code;
            }
        );
    }

    public function test_can_send_verified_notification(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $user->sendEmailVerificationNotification();

        $this->actingAs($user)->put(
            'api/verify/with-code/email',
            ['code' => $user->email_verify_code]
        );

        Notification::assertSentTo(
            $user,
            AccountVerified::class
        );
    }

    public function test_dispatches_verified_event(): void
    {
        Event::fake();

        $user = User::factory()->unverified()->create();

        $user->sendEmailVerificationNotification();

        $this->actingAs($user)->put(
            'api/verify/with-code/email',
            ['code' => $user->email_verify_code]
        );

        Event::assertDispatched(function (Verified $event) use ($user) {
            return $event->user->is($user);
        });
    }

    public function test_dispatches_verified_event_from_token(): void
    {
        Event::fake();

        $user = User::factory()->unverified()->create();

        $user->sendEmailVerificationNotification();

        $this->actingAs($user)->put(
            'api/verify/with-code/email',
            ['code' => Url::base64urlEncode($user->email_verify_code . '|' . md5(time()))]
        );

        Event::assertDispatched(function (Verified $event) use ($user) {
            return $event->user->is($user);
        });
    }

    public function test_can_load_database_notifications(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $user->notify(new TestNotifications);

        $response = $this->actingAs($user)->get('api/account/notifications');

        $response->assertOk();
        Notification::assertSentTo($user, TestNotifications::class);
    }

    public function test_can_delete_database_notifications(): void
    {
        $user = User::factory()->unverified()->create();

        $user->notify(new TestNotifications);

        $id = $user->notifications()->first()->id;
        $response = $this->actingAs($user)->delete('api/account/notifications/' . $id);

        $response->assertAccepted();
    }
}
