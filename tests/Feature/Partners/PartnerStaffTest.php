<?php

namespace Tests\Feature\Partners;

use App\Enums\AccountKind;
use App\Enums\PartnerKind;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\Identity\PortalPasswordLink;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesPartners;
use Tests\TestCase;

class PartnerStaffTest extends TestCase
{
    use MakesPartners;

    private Organisation $organisation;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Notification::fake();

        [$this->organisation, $this->owner] = $this->approvedPartner(PartnerKind::Pharmacy);
        $this->owner->forceFill(['password' => 'Password1'])->save();
    }

    public function test_approval_makes_the_contact_the_owner_and_the_session_says_so(): void
    {
        $this->assertSame($this->owner->id, $this->organisation->owner_id);

        $this->actingAs($this->owner)
            ->getJson('api/v1/portal/auth/session')
            ->assertOk()
            ->assertJsonPath('data.is_owner', true);
    }

    public function test_the_owner_invites_staff_who_set_a_password_from_the_welcome_link(): void
    {
        $id = $this->actingAs($this->owner)
            ->postJson('api/v1/portal/staff', ['name' => 'Tunde Bello', 'email' => 'Tunde@example.com'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Tunde Bello')
            ->assertJsonPath('data.email', 'tunde@example.com')
            ->assertJsonPath('data.is_owner', false)
            ->assertJsonPath('data.status', 'invited')
            ->json('data.id');

        $member = User::query()->where('uuid', $id)->firstOrFail();

        $this->assertSame($this->organisation->id, $member->organisation_id);
        $this->assertSame([AccountKind::Pharmacy], $member->kinds());

        $url = null;
        Notification::assertSentTo($member, PortalPasswordLink::class, function (PortalPasswordLink $mail) use (&$url) {
            $url = $mail->url;

            return $mail->welcome;
        });

        $this->postJson('api/v1/portal/auth/set-password', ['token' => str($url)->after('token=')->toString(), 'password' => 'Password1'])
            ->assertOk()
            ->assertJsonPath('data.account.is_owner', false)
            ->assertJsonPath('data.account.organisation_id', $this->organisation->uuid);

        $this->actingAs($member->refresh())
            ->getJson('api/v1/portal/pharmacy')
            ->assertOk()
            ->assertJsonPath('data.id', $this->organisation->uuid);
    }

    public function test_every_member_sees_the_staff_list_paged(): void
    {
        $member = $this->invite('Tunde Bello', 'tunde@example.com');

        $this->actingAs($this->owner)
            ->postJson('api/v1/portal/staff', ['name' => 'Ngozi Eze', 'email' => 'ngozi@example.com'])
            ->assertCreated();

        $this->actingAs($member)
            ->getJson('api/v1/portal/staff?limit=2')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.id', $this->owner->uuid)
            ->assertJsonPath('data.items.0.is_owner', true)
            ->assertJsonPath('data.items.0.status', 'active')
            ->assertJsonPath('data.items.1.status', 'active');

        $this->actingAs($member)
            ->getJson('api/v1/portal/staff?cursor=' . urlencode($this->staffCursor($member)))
            ->assertOk()
            ->assertJsonPath('data.items.0.status', 'invited')
            ->assertJsonPath('data.next_cursor', null);
    }

    public function test_an_invite_refuses_an_address_already_in_use(): void
    {
        $this->actingAs($this->owner)
            ->postJson('api/v1/portal/staff', ['name' => 'Ada', 'email' => strtoupper($this->owner->email)])
            ->assertConflict()
            ->assertJsonPath('error.code', 'EMAIL_TAKEN')
            ->assertJsonPath('error.field', 'email');
    }

    public function test_an_invite_is_validated(): void
    {
        $this->actingAs($this->owner)
            ->postJson('api/v1/portal/staff', ['name' => 'Ada', 'email' => 'not an email'])
            ->assertBadRequest()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.field', 'email');
    }

    public function test_only_the_owner_manages_staff(): void
    {
        $member = $this->invite('Tunde Bello', 'tunde@example.com');

        $this->actingAs($member)
            ->postJson('api/v1/portal/staff', ['name' => 'Ngozi', 'email' => 'ngozi@example.com'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'NOT_OWNER');

        $this->actingAs($member)
            ->deleteJson("api/v1/portal/staff/{$this->owner->uuid}")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'NOT_OWNER');

        $this->actingAs($member)
            ->postJson("api/v1/portal/staff/{$member->uuid}/owner")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'NOT_OWNER');
    }

    public function test_the_owner_removes_a_member_whose_sessions_end_and_whose_address_is_free_again(): void
    {
        $member = $this->invite('Tunde Bello', 'tunde@example.com');

        $session = $this->postJson('api/v1/portal/auth/sign-in', ['email' => 'tunde@example.com', 'password' => 'Password1'])
            ->assertCreated()
            ->json('data');

        $this->actingAs($this->owner)
            ->deleteJson("api/v1/portal/staff/{$member->uuid}")
            ->assertOk();

        $this->assertSoftDeleted($member);

        $this->app['auth']->forgetGuards();

        $this->postJson('api/v1/portal/auth/refresh', ['refresh_token' => $session['refresh_token']])
            ->assertUnauthorized();

        $this->postJson('api/v1/portal/auth/sign-in', ['email' => 'tunde@example.com', 'password' => 'Password1'])
            ->assertUnauthorized();

        $this->actingAs($this->owner)
            ->postJson('api/v1/portal/staff', ['name' => 'Tunde Bello', 'email' => 'tunde@example.com'])
            ->assertCreated();
    }

    public function test_the_owner_cannot_be_removed(): void
    {
        $this->actingAs($this->owner)
            ->deleteJson("api/v1/portal/staff/{$this->owner->uuid}")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'STAFF_IS_OWNER');
    }

    public function test_the_owner_hands_ownership_to_an_active_member(): void
    {
        $member = $this->invite('Tunde Bello', 'tunde@example.com');

        $this->actingAs($this->owner)
            ->postJson("api/v1/portal/staff/{$member->uuid}/owner")
            ->assertOk()
            ->assertJsonPath('data.is_owner', true);

        $this->assertSame($member->id, $this->organisation->refresh()->owner_id);

        $this->actingAs($this->owner)
            ->postJson('api/v1/portal/staff', ['name' => 'Ngozi', 'email' => 'ngozi@example.com'])
            ->assertForbidden();
    }

    public function test_ownership_needs_a_member_who_has_set_a_password(): void
    {
        $id = $this->actingAs($this->owner)
            ->postJson('api/v1/portal/staff', ['name' => 'Tunde Bello', 'email' => 'tunde@example.com'])
            ->json('data.id');

        $this->actingAs($this->owner)
            ->postJson("api/v1/portal/staff/{$id}/owner")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'STAFF_NOT_ACTIVE');
    }

    public function test_another_partners_member_is_not_found(): void
    {
        [, $other] = $this->approvedPartner(PartnerKind::Laboratory);

        $this->actingAs($this->owner)
            ->deleteJson("api/v1/portal/staff/{$other->uuid}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'STAFF_NOT_FOUND');

        $this->actingAs($this->owner)
            ->postJson("api/v1/portal/staff/{$other->uuid}/owner")
            ->assertNotFound();
    }

    public function test_the_team_and_app_accounts_are_refused(): void
    {
        $this->actingAs($this->admin())
            ->getJson('api/v1/portal/staff')
            ->assertForbidden();

        $patient = User::factory()->app()->create();

        $this->actingAs($patient)
            ->getJson('api/v1/portal/staff')
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->getJson('api/v1/portal/staff')->assertUnauthorized();
    }

    private function staffCursor(User $member): string
    {
        return $this->actingAs($member)
            ->getJson('api/v1/portal/staff?limit=2')
            ->json('data.next_cursor');
    }

    private function invite(string $name, string $email): User
    {
        $id = $this->actingAs($this->owner)
            ->postJson('api/v1/portal/staff', ['name' => $name, 'email' => $email])
            ->json('data.id');

        $member = User::query()->where('uuid', $id)->firstOrFail();
        $member->forceFill(['password' => 'Password1'])->save();

        return $member;
    }
}
