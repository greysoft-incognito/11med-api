<?php

namespace Tests\Feature\Partners;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\Identity\PortalPasswordLink;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesPartners;
use Tests\TestCase;

class PartnerWelcomeTest extends TestCase
{
    use MakesPartners;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Notification::fake();
    }

    public function test_the_team_resends_the_welcome_link_and_the_earlier_one_stops_working(): void
    {
        [$partner, $account] = $this->approvedPartner();
        $first = $this->lastWelcomeToken($account);

        $this->actingAs($this->admin())
            ->postJson("api/v1/portal/admin/partners/{$partner->uuid}/welcome")
            ->assertOk()
            ->assertJsonPath('data.id', $partner->uuid)
            ->assertJsonPath('data.dev_set_password_link', null);

        $fresh = $this->lastWelcomeToken($account);

        $this->assertNotSame($first, $fresh);

        $this->postJson('api/v1/portal/auth/set-password', ['token' => $first, 'password' => 'Password1'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'RESET_INVALID');

        $this->postJson('api/v1/portal/auth/set-password', ['token' => $fresh, 'password' => 'Password1'])
            ->assertOk();
    }

    public function test_there_is_no_welcome_to_resend_once_the_password_is_set(): void
    {
        [$partner, $account] = $this->approvedPartner();
        $account->forceFill(['password' => 'Password1'])->save();

        $this->actingAs($this->admin())
            ->postJson("api/v1/portal/admin/partners/{$partner->uuid}/welcome")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'WELCOME_ALREADY_USED');
    }

    public function test_only_an_approved_partner_is_sent_a_welcome(): void
    {
        $admin = $this->admin();
        $pending = $this->postJson('api/v1/partners/apply', $this->application())->json('data.id');

        $this->actingAs($admin)
            ->postJson("api/v1/portal/admin/partners/{$pending}/welcome")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'WELCOME_NOT_APPROVED');

        [$suspended] = $this->approvedPartner();
        $this->suspend($suspended);

        $this->actingAs($admin)
            ->postJson("api/v1/portal/admin/partners/{$suspended->uuid}/welcome")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'WELCOME_NOT_APPROVED');

        $this->actingAs($admin)
            ->postJson('api/v1/portal/admin/partners/0190a0a0-0000-7000-8000-000000000000/welcome')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'PARTNER_NOT_FOUND');
    }

    public function test_only_the_team_resends_a_welcome(): void
    {
        [$partner, $pharmacy] = $this->approvedPartner();

        $this->postJson("api/v1/portal/admin/partners/{$partner->uuid}/welcome")->assertUnauthorized();

        $this->actingAs($pharmacy)
            ->postJson("api/v1/portal/admin/partners/{$partner->uuid}/welcome")
            ->assertForbidden();

        $this->actingAs(User::factory()->app(Role::Patient)->create())
            ->postJson("api/v1/portal/admin/partners/{$partner->uuid}/welcome")
            ->assertForbidden();
    }

    private function lastWelcomeToken(User $account): string
    {
        $url = null;

        Notification::assertSentTo($account, PortalPasswordLink::class, function (PortalPasswordLink $mail) use (&$url) {
            $url = $mail->url;

            return $mail->welcome;
        });

        return str($url)->after('token=')->toString();
    }
}
