<?php

namespace Tests\Feature\Settings;

use App\Enums\AccountKind;
use App\Enums\AdminPermission;
use App\Models\User;
use App\Services\Audit\AuditLog;
use App\Services\Settings\PlatformSettings;
use App\Services\Subscriptions\Entitlements;
use Tests\Concerns\MakesAdmins;
use Tests\Fakes\RecordingAuditLog;
use Tests\TestCase;

class AdminSettingTest extends TestCase
{
    use MakesAdmins;

    private RecordingAuditLog $audit;

    private User $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-01T09:00:00Z');

        $this->audit = new RecordingAuditLog;
        $this->app->instance(AuditLog::class, $this->audit);
        $this->app->forgetInstance(PlatformSettings::class);

        $this->team = $this->teamMember();
    }

    public function test_every_setting_reads_with_its_default(): void
    {
        $this->actingAs($this->team)
            ->getJson('api/v1/portal/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.0', [
                'key' => 'free_tier_medications',
                'group' => 'free_tier',
                'label' => 'Active medications on the free tier',
                'type' => 'integer',
                'value' => 3,
                'default' => 3,
                'min' => 1,
                'max' => 50,
                'overridden' => false,
                'updated_at' => null,
            ])
            ->assertJsonFragment(['key' => 'consultation_commission_percent', 'type' => 'number', 'value' => 15]);
    }

    public function test_a_change_takes_effect_is_audited_and_resets_to_the_default(): void
    {
        $patient = User::factory()
            ->app(AccountKind::Patient)
            ->create();

        $this->actingAs($this->team)
            ->putJson('api/v1/portal/admin/settings/free_tier_medications', ['value' => 5])
            ->assertOk()
            ->assertJsonPath('data.value', 5)
            ->assertJsonPath('data.default', 3)
            ->assertJsonPath('data.overridden', true)
            ->assertJsonPath('data.updated_at', '2026-12-01T09:00:00.000Z');

        $this->assertSame(5, app(Entitlements::class)->medicationLimit($patient));
        $this->assertSame([['key' => 'free_tier_medications', 'from' => 3, 'to' => 5]], array_column($this->audit->records, 'meta'));

        $this->app->forgetInstance(PlatformSettings::class);
        config(['elevenmed.free_tier.medications' => 3]);
        app(PlatformSettings::class)->apply();

        $this->assertSame(5, config('elevenmed.free_tier.medications'), 'A fresh boot lays the stored value over the config file.');

        $this->actingAs($this->team)
            ->putJson('api/v1/portal/admin/settings/free_tier_medications', ['value' => null])
            ->assertOk()
            ->assertJsonPath('data.value', 3)
            ->assertJsonPath('data.overridden', false);

        $this->assertSame(3, config('elevenmed.free_tier.medications'));

        $this->actingAs($this->team)
            ->putJson('api/v1/portal/admin/settings/consultation_commission_percent', ['value' => 12.5])
            ->assertOk()
            ->assertJsonPath('data.value', 12.5);
    }

    public function test_values_are_checked_against_their_bounds_and_type(): void
    {
        foreach ([['free_tier_medications', 0], ['free_tier_medications', 2.5], ['otp_max_resends', 'many'], ['consultation_commission_percent', 51]] as [$key, $value]) {
            $this->actingAs($this->team)
                ->putJson("api/v1/portal/admin/settings/{$key}", ['value' => $value])
                ->assertStatus(400)
                ->assertJsonPath('error.field', 'value');
        }

        $this->actingAs($this->team)
            ->putJson('api/v1/portal/admin/settings/app_name', ['value' => 1])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'SETTING_NOT_FOUND');
    }

    public function test_viewing_does_not_allow_editing_and_other_roles_are_refused(): void
    {
        $viewer = $this->teamMember(AdminPermission::SettingsView);

        $this->actingAs($viewer)->getJson('api/v1/portal/admin/settings')->assertOk();
        $this->actingAs($viewer)->putJson('api/v1/portal/admin/settings/otp_max_resends', ['value' => 4])->assertForbidden();

        $this->actingAs(User::factory()->app(AccountKind::Patient)->create())
            ->getJson('api/v1/portal/admin/settings')
            ->assertForbidden();
    }
}
