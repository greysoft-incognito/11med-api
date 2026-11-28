<?php

namespace Tests\Feature\Identity;

use App\Enums\AccountKind;
use App\Models\User;
use App\Notifications\Identity\EmailCode;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PortalAccountTest extends TestCase
{
    private User $desk;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config(['elevenmed.otp.fixed_code' => '123456']);

        $this->desk = User::factory()
            ->portal(AccountKind::Pharmacy)
            ->create(['email' => 'desk@pharmacy.ng', 'full_name' => 'Ada Desk']);
    }

    /**
     * @return array{access_token: string, refresh_token: string}
     */
    private function signIn(string $email = 'desk@pharmacy.ng', string $password = 'Password1'): array
    {
        return $this->postJson('api/v1/portal/auth/sign-in', ['email' => $email, 'password' => $password])
            ->assertCreated()
            ->json('data');
    }

    public function test_the_name_changes(): void
    {
        $this->actingAs($this->desk, 'sanctum')
            ->patchJson('api/v1/portal/account', ['name' => 'Ada Okafor'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ada Okafor')
            ->assertJsonPath('data.email', 'desk@pharmacy.ng');

        $this->patchJson('api/v1/portal/account', ['name' => ''])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'name');
    }

    public function test_the_email_moves_once_the_code_sent_to_it_comes_back(): void
    {
        $this->actingAs($this->desk, 'sanctum')
            ->postJson('api/v1/portal/account/email', ['email' => 'ada@pharmacy.ng', 'password' => 'Password1'])
            ->assertOk()
            ->assertJsonPath('data.resends_remaining', 2);

        Notification::assertSentTo(new AnonymousNotifiable, EmailCode::class, fn($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'ada@pharmacy.ng');

        $this->assertSame('desk@pharmacy.ng', $this->desk->refresh()->email);

        $this->postJson('api/v1/portal/account/email/verify', ['code' => '123456'])
            ->assertOk()
            ->assertJsonPath('data.email', 'ada@pharmacy.ng');

        $this->signIn('ada@pharmacy.ng');
        $this->postJson('api/v1/portal/auth/sign-in', ['email' => 'desk@pharmacy.ng', 'password' => 'Password1'])->assertUnauthorized();
    }

    public function test_the_email_change_refuses_a_wrong_password_a_taken_address_and_the_same_one(): void
    {
        User::factory()
            ->portal(AccountKind::Laboratory)
            ->create(['email' => 'taken@lab.ng']);

        $this->actingAs($this->desk, 'sanctum')
            ->postJson('api/v1/portal/account/email', ['email' => 'ada@pharmacy.ng', 'password' => 'Wrong1234'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PASSWORD_INCORRECT');

        $this->postJson('api/v1/portal/account/email', ['email' => 'TAKEN@lab.ng', 'password' => 'Password1'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'EMAIL_TAKEN')
            ->assertJsonPath('error.field', 'email');

        $this->postJson('api/v1/portal/account/email', ['email' => 'Desk@pharmacy.ng', 'password' => 'Password1'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'EMAIL_UNCHANGED');

        $this->postJson('api/v1/portal/account/email/verify', ['code' => '123456'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'EMAIL_CHANGE_NOT_STARTED');
    }

    public function test_a_password_change_signs_out_every_other_session(): void
    {
        $other = $this->signIn();
        $mine = $this->signIn();

        $this->withToken($mine['access_token'])
            ->postJson('api/v1/portal/account/password', ['current_password' => 'Wrong1234', 'new_password' => 'newpass99'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PASSWORD_INCORRECT')
            ->assertJsonPath('error.field', 'current_password');

        $this->withToken($mine['access_token'])
            ->postJson('api/v1/portal/account/password', ['current_password' => 'Password1', 'new_password' => 'short'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'new_password');

        $this->withToken($mine['access_token'])
            ->postJson('api/v1/portal/account/password', ['current_password' => 'Password1', 'new_password' => 'newpass99'])
            ->assertOk();

        $this->postJson('api/v1/portal/auth/refresh', ['refresh_token' => $other['refresh_token']])->assertUnauthorized();
        $this->postJson('api/v1/portal/auth/refresh', ['refresh_token' => $mine['refresh_token']])->assertOk();

        $this->signIn(password: 'newpass99');
    }

    public function test_only_portal_accounts_use_the_page(): void
    {
        $this->patchJson('api/v1/portal/account', ['name' => 'Someone'])->assertUnauthorized();

        $patient = User::factory()
            ->app()
            ->create();

        $this->actingAs($patient, 'sanctum')
            ->patchJson('api/v1/portal/account', ['name' => 'Someone'])
            ->assertForbidden();
    }
}
