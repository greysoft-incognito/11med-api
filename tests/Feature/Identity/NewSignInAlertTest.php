<?php

namespace Tests\Feature\Identity;

use App\Enums\AccountKind;
use App\Models\AppNotification;
use App\Models\User;
use App\Notifications\Identity\NewSignInEmail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NewSignInAlertTest extends TestCase
{
    private User $patient;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->patient = User::factory()
            ->app()
            ->create(['phone' => '08031234567', 'email' => 'ada@example.com', 'email_verified_at' => now()]);
    }

    private function login(string $installId): void
    {
        $this->withHeaders(['X-Device-Name' => 'Tecno Spark 10', 'X-Device-Platform' => 'android'])
            ->postJson('api/v1/auth/login', ['phone_number' => '08031234567', 'password' => 'Password1', 'device_id' => $installId])
            ->assertOk();
    }

    public function test_a_new_device_is_announced_and_a_known_one_is_not(): void
    {
        $phone = fake()->uuid();

        $this->login($phone);

        $this->assertSame(0, AppNotification::count());
        Notification::assertNothingSent();

        $this->login($phone);

        $this->assertSame(0, AppNotification::count());

        $this->login(fake()->uuid());

        $notification = AppNotification::sole();

        $this->assertSame('account', $notification->category->value);
        $this->assertSame('New sign-in to your account', $notification->title);
        $this->assertStringContainsString('Tecno Spark 10', $notification->body);
        $this->assertDoesNotMatchRegularExpression('/\d{6}/', $notification->body);

        Notification::assertSentTo($this->patient, NewSignInEmail::class, fn(NewSignInEmail $mail) => $mail->device === 'Tecno Spark 10');
    }

    public function test_no_email_without_a_confirmed_address(): void
    {
        $this->patient->forceFill(['email_verified_at' => null])->save();

        $this->login(fake()->uuid());
        $this->login(fake()->uuid());

        $this->assertSame(1, AppNotification::count());
        Notification::assertNothingSent();
    }

    public function test_a_portal_account_is_told_of_a_new_browser(): void
    {
        $desk = User::factory()
            ->portal(AccountKind::Laboratory)
            ->create(['email' => 'desk@lab.ng', 'email_verified_at' => now()]);

        foreach (['Firefox/130.0', 'Firefox/130.0', 'Chrome/130.0'] as $agent) {
            $this->withHeaders(['User-Agent' => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) {$agent}"])
                ->postJson('api/v1/portal/auth/sign-in', ['email' => 'desk@lab.ng', 'password' => 'Password1'])
                ->assertCreated();
        }

        Notification::assertSentToTimes($desk, NewSignInEmail::class, 1);
    }
}
