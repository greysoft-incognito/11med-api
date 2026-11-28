<?php

namespace Tests\Feature;

use App\Helpers\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class NotificationTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_notification_templates(): void
    {
        $admin = User::factory()->unverified()->staff()->create();

        $this->artisan('app:sync-roles');
        $admin->syncPermissions(config('permission-defs.permissions', []));

        $response = $this->actingAs($admin)->get('api/admin/configurations/notifications/templates');

        $response->assertOk();
        $response->assertJsonIsArray('data');
    }

    public function test_can_get_notification_template(): void
    {
        $admin = User::factory()->unverified()->staff()->create();

        $this->artisan('app:sync-roles');
        $admin->syncPermissions(config('permission-defs.permissions', []));

        $key = array_keys(config('messages'))[0];
        $response = $this->actingAs($admin)->get('api/admin/configurations/notifications/templates/' . $key);
        $response->assertOk();
    }

    public function test_can_parse_notification_template(): void
    {
        $admin = User::factory()->unverified()->staff()->create();

        $this->artisan('app:sync-roles');
        $admin->syncPermissions(config('permission-defs.permissions', []));

        $key = array_keys(config('messages'))[3];

        $message = Provider::messageParser(
            $key,
            $admin,
            [
                'code' => '0000',
                'token' => '0000-0000-0000-0000',
                'label' => 'email address',
                'app_url' => config('app.frontend_url', config('app.url')),
                'app_name' => Provider::config('app_name') ?: config('app.name'),
                'duration' => now()->addMinute()->longAbsoluteDiffForHumans(),
            ]
        );

        $this->assertStringContainsString('0000', $message->toPlain());
        $this->assertStringContainsString('0000-0000-0000-0000', $message->toMail()->render());
        $this->assertStringContainsString('©' . date('Y'), $message->toMail()->render());
        $this->assertInstanceOf(MailMessage::class, $message->toMail());
    }
}
