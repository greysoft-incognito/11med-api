<?php

namespace Tests\Feature\Identity;

use App\Enums\AuthClient;
use App\Enums\ConsentContext;
use App\Enums\PartnerKind;
use App\Models\ConsentRecord;
use App\Models\User;
use App\Services\Identity\SessionTokens;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesPartners;
use Tests\TestCase;

class PolicyConsentTest extends TestCase
{
    use MakesPartners;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Notification::fake();

        config(['elevenmed.consent.policy_version' => '2026-07-01']);
    }

    public function test_an_app_account_is_asked_again_when_the_policy_version_moves(): void
    {
        $patient = $this->patientWhoConsented('2026-07-01');
        $token = app(SessionTokens::class)->issue($patient, AuthClient::App)->accessToken;

        $this->withToken($token)
            ->getJson('api/v1/auth/session')
            ->assertOk()
            ->assertJsonPath('data.user.pending_policy_version', null);

        config(['elevenmed.consent.policy_version' => '2026-12-01']);

        $this->withToken($token)
            ->getJson('api/v1/auth/session')
            ->assertJsonPath('data.user.pending_policy_version', '2026-12-01');

        $this->actingAs($patient)
            ->postJson('api/v1/auth/consent', ['policy_version' => '2026-12-01'])
            ->assertOk()
            ->assertJsonPath('data.pending_policy_version', null);

        $record = ConsentRecord::query()
            ->where('user_id', $patient->id)
            ->where('policy_version', '2026-12-01')
            ->sole();

        $this->assertSame(ConsentContext::PolicyUpdate, $record->context);
        $this->assertNotNull($record->accepted_at);

        $this->actingAs($patient)
            ->postJson('api/v1/auth/consent', ['policy_version' => '2026-12-01'])
            ->assertOk();

        $this->assertSame(2, ConsentRecord::query()->where('user_id', $patient->id)->count());
    }

    public function test_accepting_an_older_version_is_refused(): void
    {
        $patient = $this->patientWhoConsented('2026-07-01');
        config(['elevenmed.consent.policy_version' => '2026-12-01']);

        $this->actingAs($patient)
            ->postJson('api/v1/auth/consent', ['policy_version' => '2026-07-01'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'POLICY_VERSION_OUTDATED')
            ->assertJsonPath('error.field', 'policy_version');

        $this->actingAs($patient)
            ->postJson('api/v1/auth/consent', [])
            ->assertBadRequest()
            ->assertJsonPath('error.field', 'policy_version');
    }

    public function test_a_partner_account_accepts_from_the_portal(): void
    {
        [, $account] = $this->approvedPartner(PartnerKind::Laboratory);

        $this->actingAs($account)
            ->getJson('api/v1/portal/auth/session')
            ->assertJsonPath('data.pending_policy_version', '2026-07-01');

        $this->actingAs($account)
            ->postJson('api/v1/portal/auth/consent', ['policy_version' => '2026-07-01'])
            ->assertOk()
            ->assertJsonPath('data.pending_policy_version', null)
            ->assertJsonPath('data.id', $account->uuid);
    }

    public function test_the_team_is_not_asked_and_cannot_use_either_route(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->getJson('api/v1/portal/auth/session')
            ->assertJsonPath('data.pending_policy_version', null);

        $this->actingAs($admin)
            ->postJson('api/v1/portal/auth/consent', ['policy_version' => '2026-07-01'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->postJson('api/v1/auth/consent', ['policy_version' => '2026-07-01'])
            ->assertForbidden();
    }

    public function test_an_app_account_cannot_use_the_portal_route(): void
    {
        $this->actingAs($this->patientWhoConsented('2026-07-01'))
            ->postJson('api/v1/portal/auth/consent', ['policy_version' => '2026-07-01'])
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->postJson('api/v1/auth/consent', ['policy_version' => '2026-07-01'])
            ->assertUnauthorized();
    }

    private function patientWhoConsented(string $version): User
    {
        $patient = User::factory()->app()->create();

        ConsentRecord::create([
            'user_id' => $patient->id,
            'context' => ConsentContext::Register,
            'policy_version' => $version,
            'accepted_at' => now(),
            'recorded_at' => now(),
        ]);

        return $patient;
    }
}
