<?php

namespace Tests\Feature\Announcements;

use App\Enums\AccountKind;
use App\Enums\AdminPermission;
use App\Enums\NotificationCategory;
use App\Models\Announcement;
use App\Models\User;
use App\Services\Audit\AuditLog;
use App\Services\Notifications\Notifier;
use Tests\Concerns\MakesAdmins;
use Tests\Fakes\RecordingAuditLog;
use Tests\Fakes\RecordingNotifier;
use Tests\TestCase;

class AdminAnnouncementTest extends TestCase
{
    use MakesAdmins;

    private RecordingNotifier $notifier;

    private RecordingAuditLog $audit;

    private User $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-01T09:00:00Z');

        $this->notifier = new RecordingNotifier;
        $this->audit = new RecordingAuditLog;
        $this->app->instance(Notifier::class, $this->notifier);
        $this->app->instance(AuditLog::class, $this->audit);

        $this->team = $this->teamMember();
    }

    public function test_the_preview_counts_live_accounts_in_the_audience(): void
    {
        $this->users(AccountKind::Patient, 3);
        $this->users(AccountKind::Doctor, 2);
        User::factory()->app(AccountKind::Patient)->create(['suspended_at' => now()]);
        User::factory()->portal(AccountKind::Pharmacy)->create();

        foreach (['patients' => 3, 'doctors' => 2, 'app_users' => 5, 'pharmacies' => 1, 'laboratories' => 0] as $audience => $count) {
            $this->actingAs($this->team)
                ->postJson('api/v1/portal/admin/announcements/preview', ['audience' => $audience])
                ->assertOk()
                ->assertJsonPath('data', ['audience' => $audience, 'recipients' => $count]);
        }
    }

    public function test_an_announcement_sent_now_reaches_every_account_once(): void
    {
        $patients = $this->users(AccountKind::Patient, 3);
        $doctor = User::factory()->app(AccountKind::Doctor)->create();

        $id = $this->actingAs($this->team)
            ->postJson('api/v1/portal/admin/announcements', $this->body(['audience' => 'patients', 'push' => false]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.recipients', 3)
            ->assertJsonPath('data.created_by_name', $this->team->full_name)
            ->json('data.id');

        $this->assertEqualsCanonicalizing(array_map(fn(User $user) => $user->id, $patients), array_column($this->notifier->sent, 'to'));
        $this->assertNotContains($doctor->id, array_column($this->notifier->sent, 'to'));
        $this->assertSame(NotificationCategory::Announcement, $this->notifier->sent[0]['category']);
        $this->assertSame(['target_id' => $id, 'dedupe_key' => "announcement:{$id}", 'push' => false], $this->notifier->sent[0]['data']);
        $this->assertSame(['announcement.sent'], $this->audit->actions());
    }

    public function test_a_scheduled_announcement_goes_out_at_its_time_or_is_withdrawn(): void
    {
        $this->users(AccountKind::Doctor, 2);

        $later = $this->actingAs($this->team)
            ->postJson('api/v1/portal/admin/announcements', $this->body(['send_at' => '2026-12-01T12:00:00.000Z']))
            ->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.send_at', '2026-12-01T12:00:00.000Z')
            ->json('data.id');

        $withdrawn = $this->actingAs($this->team)
            ->postJson('api/v1/portal/admin/announcements', $this->body(['send_at' => '2026-12-02T12:00:00.000Z']))
            ->json('data.id');

        $this->actingAs($this->team)
            ->postJson("api/v1/portal/admin/announcements/{$withdrawn}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->artisan('announcements:dispatch')->assertSuccessful();
        $this->assertSame([], $this->notifier->sent);

        $this->travelTo('2026-12-03T12:00:00Z');
        $this->artisan('announcements:dispatch')->assertSuccessful();

        $this->assertCount(2, $this->notifier->sent);
        $this->assertSame('sent', Announcement::firstWhere('uuid', $later)->status->value);
        $this->assertSame('cancelled', Announcement::firstWhere('uuid', $withdrawn)->status->value);

        $this->actingAs($this->team)
            ->postJson("api/v1/portal/admin/announcements/{$later}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ANNOUNCEMENT_NOT_SCHEDULED');

        $this->actingAs($this->team)
            ->getJson('api/v1/portal/admin/announcements?limit=1')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.0.id', $withdrawn);

        $this->assertSame(['announcement.scheduled', 'announcement.scheduled', 'announcement.cancelled'], $this->audit->actions());
    }

    public function test_the_body_is_validated(): void
    {
        foreach ([['title' => 'x'], ['audience' => 'everyone'], ['send_at' => '2026-11-30T09:00:00.000Z'], ['send_at' => '2027-06-01T09:00:00.000Z'], ['push' => 'often']] as $change) {
            $field = array_key_first($change);

            $this->actingAs($this->team)
                ->postJson('api/v1/portal/admin/announcements', $this->body($change))
                ->assertStatus(400)
                ->assertJsonPath('error.field', $field);
        }
    }

    public function test_viewing_does_not_allow_sending_and_other_roles_are_refused(): void
    {
        $viewer = $this->teamMember(AdminPermission::AnnouncementsView);

        $this->actingAs($viewer)->getJson('api/v1/portal/admin/announcements')->assertOk();
        $this->actingAs($viewer)->postJson('api/v1/portal/admin/announcements/preview', ['audience' => 'patients'])->assertOk();
        $this->actingAs($viewer)->postJson('api/v1/portal/admin/announcements', $this->body())->assertForbidden();

        $this->actingAs(User::factory()->portal(AccountKind::Laboratory)->create())
            ->getJson('api/v1/portal/admin/announcements')
            ->assertForbidden();
    }

    /**
     * @return list<User>
     */
    private function users(AccountKind $role, int $count): array
    {
        return User::factory()
            ->count($count)
            ->app($role)
            ->create()
            ->all();
    }

    private function body(array $overrides = []): array
    {
        return [
            'title' => 'New clinic hours',
            'body' => 'Consultations are open from 7am to 10pm from Monday.',
            'audience' => 'doctors',
            'push' => true,
            'send_at' => null,
            ...$overrides,
        ];
    }
}
