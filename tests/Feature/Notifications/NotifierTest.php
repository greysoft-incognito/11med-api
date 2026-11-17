<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationCategory;
use App\Jobs\Notifications\DeliverPush;
use App\Models\AppNotification;
use App\Models\Device;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotifierTest extends TestCase
{
    private const TARGET = '0b7f0f0e-5d6a-4c43-9d1e-2f4a8f0c9a11';

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        array_map('unlink', $this->files);

        parent::tearDown();
    }

    public function test_send_writes_the_history_row_and_pushes_its_id(): void
    {
        Queue::fake();
        $user = $this->patient();

        $id = $this->send($user);

        $row = AppNotification::sole();
        $this->assertSame($row->uuid, $id);
        $this->assertSame(self::TARGET, $row->target_id);

        Queue::assertPushed(DeliverPush::class, function (DeliverPush $job) use ($user, $id) {
            return $job->userId === $user->id && $job->message->payload() === [
                'kind' => 'push',
                'category' => 'consultation',
                'notification_id' => $id,
                'target_id' => self::TARGET,
            ];
        });
    }

    public function test_a_repeated_dedupe_key_writes_and_pushes_once(): void
    {
        Queue::fake();
        $user = $this->patient();

        $first = $this->send($user, ['dedupe_key' => 'accepted:1']);
        $second = $this->send($user, ['dedupe_key' => 'accepted:1']);

        $this->assertSame($first, $second);
        $this->assertSame(1, AppNotification::count());
        Queue::assertPushed(DeliverPush::class, 1);
    }

    public function test_preferences_decide_the_row_and_the_push(): void
    {
        Queue::fake();
        $user = $this->patient();

        NotificationPreference::create(['user_id' => $user->id, 'category' => 'consultation', 'push' => false, 'sms' => false, 'in_app' => true]);
        NotificationPreference::create(['user_id' => $user->id, 'category' => 'order', 'push' => true, 'sms' => false, 'in_app' => false]);

        $this->assertNotNull($this->send($user));
        Queue::assertNothingPushed();

        $this->assertNull(app(Notifier::class)->send($user, NotificationCategory::Order, 'Order picked up', 'A rider is on the way.', ['target_id' => self::TARGET]));
        $this->assertSame(1, AppNotification::count());

        Queue::assertPushed(DeliverPush::class, fn(DeliverPush $job) => Str::isUuid($job->message->notificationId));
    }

    public function test_push_false_writes_the_row_only(): void
    {
        Queue::fake();
        $user = $this->patient();

        $this->send($user, ['push' => false]);

        $this->assertSame(1, AppNotification::count());
        Queue::assertNothingPushed();
    }

    public function test_without_credentials_pushes_are_logged_by_id_only(): void
    {
        Http::fake();
        Log::spy();
        $user = $this->patient();
        $this->device($user, 'fcm');

        $this->send($user);

        Http::assertNothingSent();
        Log::shouldHaveReceived('info')
            ->withArgs(fn(string $message, array $context) => $message === 'push.logged'
                && $context['category'] === 'consultation'
                && ! str_contains(json_encode($context), 'Dr Bakare'))
            ->once();
    }

    public function test_apns_receives_the_alert_and_payload_on_the_registered_token(): void
    {
        $this->configureApns();
        Http::fake(['api.sandbox.push.apple.com/*' => Http::response('', 200)]);
        $user = $this->patient();
        $this->device($user, 'apns', 'ios-token');

        $id = $this->send($user);

        Http::assertSent(function (Request $request) use ($id) {
            return $request->url() === 'https://api.sandbox.push.apple.com/3/device/ios-token'
                && $request->header('apns-topic')[0] === 'ng.elevenmed.app'
                && str_starts_with($request->header('authorization')[0], 'bearer ')
                && $request['aps']['alert'] === ['title' => 'Dr Bakare accepted your consultation', 'body' => 'Open the app for details.']
                && $request['notification_id'] === $id
                && $request['target_id'] === self::TARGET
                && $request['kind'] === 'push';
        });
        $this->assertSame(1, Device::count());
    }

    public function test_a_token_apns_reports_unregistered_is_dropped(): void
    {
        $this->configureApns();
        Http::fake(['api.sandbox.push.apple.com/*' => Http::response(['reason' => 'Unregistered'], 410)]);
        $user = $this->patient();
        $this->device($user, 'apns');

        $this->send($user);

        $this->assertSame(0, Device::count());
    }

    public function test_fcm_sends_on_the_updates_channel_with_string_data(): void
    {
        $this->configureFcm();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'google-token']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/elevenmed/messages/1']),
        ]);
        $user = $this->patient();
        $this->device($user, 'fcm', 'android-token');

        $id = app(Notifier::class)->send($user, NotificationCategory::Account, 'Password changed', 'Your password was changed.');

        Http::assertSent(function (Request $request) use ($id) {
            if (! str_starts_with($request->url(), 'https://fcm.googleapis.com/v1/projects/elevenmed/messages:send')) {
                return false;
            }

            $message = $request['message'];

            return $request->header('Authorization')[0] === 'Bearer google-token'
                && $message['token'] === 'android-token'
                && $message['android']['notification']['channel_id'] === 'updates'
                && $message['data']['notification_id'] === $id
                && ! array_key_exists('target_id', $message['data'])
                && json_decode($message['data']['body'], true)['target_id'] === null;
        });
    }

    public function test_a_token_fcm_reports_unregistered_is_dropped_and_other_failures_keep_it(): void
    {
        $this->configureFcm();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'google-token']),
            'fcm.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['status' => 'UNAVAILABLE', 'message' => 'Try later']], 503)
                ->push(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404),
        ]);
        $user = $this->patient();
        $this->device($user, 'fcm');

        $this->send($user);
        $this->assertSame(1, Device::count());

        $this->send($user);
        $this->assertSame(0, Device::count());
    }

    public function test_a_token_replaced_while_the_push_was_out_is_kept(): void
    {
        $this->configureApns();
        $user = $this->patient();
        $device = $this->device($user, 'apns', 'old-token');

        Http::fake(function () use ($device) {
            Device::whereKey($device->id)->update(['push_token' => 'new-token']);

            return Http::response(['reason' => 'BadDeviceToken'], 400);
        });

        $this->send($user);

        $this->assertSame('new-token', Device::sole()->push_token);
    }

    public function test_erasing_the_user_removes_their_history_and_preferences(): void
    {
        $user = $this->patient();
        $this->send($user, ['push' => false]);
        NotificationPreference::create(['user_id' => $user->id, 'category' => 'order', 'push' => false, 'sms' => false, 'in_app' => true]);

        $user->forceDelete();

        $this->assertSame(0, AppNotification::withTrashed()->count());
        $this->assertSame(0, NotificationPreference::withTrashed()->count());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function send(User $user, array $data = []): ?string
    {
        return app(Notifier::class)->send(
            $user,
            NotificationCategory::Consultation,
            'Dr Bakare accepted your consultation',
            'Open the app for details.',
            ['target_id' => self::TARGET, ...$data],
        );
    }

    private function patient(): User
    {
        return User::factory()
            ->app()
            ->create();
    }

    private function device(User $user, string $provider, string $token = 'token'): Device
    {
        return Device::create([
            'user_id' => $user->id,
            'install_id' => (string) Str::uuid(),
            'platform' => $provider === 'apns' ? 'ios' : 'android',
            'push_provider' => $provider,
            'push_token' => $token,
            'app_version' => '1.1.0',
            'locale' => 'en',
            'registered_at' => now(),
        ]);
    }

    private function configureApns(): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($key, $pem);

        config([
            'elevenmed.push.enabled' => true,
            'elevenmed.push.apns.key_path' => $this->file($pem),
            'elevenmed.push.apns.key_id' => 'ABC123DEFG',
            'elevenmed.push.apns.team_id' => 'TEAM123456',
        ]);
    }

    private function configureFcm(): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        openssl_pkey_export($key, $pem);

        config([
            'elevenmed.push.enabled' => true,
            'elevenmed.push.fcm.credentials_path' => $this->file(json_encode([
                'project_id' => 'elevenmed',
                'client_email' => 'push@elevenmed.iam.gserviceaccount.com',
                'private_key' => $pem,
            ])),
        ]);
    }

    private function file(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'push');
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }
}
