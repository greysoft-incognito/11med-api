<?php

namespace Tests\Feature\Partners;

use App\Enums\AccountKind;
use App\Enums\PartnerKind;
use App\Events\Partners\PartnerApproved;
use App\Events\Partners\PartnerReinstated;
use App\Events\Partners\PartnerSuspended;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\Identity\PortalAccessGranted;
use App\Notifications\Identity\PortalPasswordLink;
use App\Notifications\Partners\PartnerStatusChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesPartners;
use Tests\TestCase;

class PartnerTest extends TestCase
{
    use MakesPartners;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Notification::fake();
    }

    public function test_anyone_applies_with_an_uploaded_licence_and_the_application_is_pending(): void
    {
        $body = $this->application(['contact_email' => 'Desk@Medplus.ng']);

        $this->postJson('api/v1/partners/apply', $body)
            ->assertCreated()
            ->assertJsonPath('data.kind', 'pharmacy')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.contact_phone', '08031234567')
            ->assertJsonPath('data.contact_email', 'desk@medplus.ng')
            ->assertJsonPath('data.reviewed_at', null)
            ->assertJsonPath('data.dev_set_password_link', null)
            ->assertJsonStructure(['data' => ['id', 'licence_url', 'applied_at']]);

        $this->assertSame(0, User::query()->whereNotNull('organisation_id')->count());
    }

    public function test_a_licence_applied_twice_a_foreign_licence_or_a_taken_email_is_refused(): void
    {
        $body = $this->application(['licence_number' => 'PCN-123456']);

        $this->postJson('api/v1/partners/apply', $body)->assertCreated();

        $this->postJson('api/v1/partners/apply', $this->application(['licence_number' => 'pcn-123456']))
            ->assertConflict()
            ->assertJsonPath('error.code', 'LICENCE_APPLIED')
            ->assertJsonPath('error.field', 'licence_number');

        $this->postJson('api/v1/partners/apply', $this->application(['licence_url' => $body['licence_url']]))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'MEDIA_NOT_FOUND')
            ->assertJsonPath('error.field', 'licence_url');

        $taken = User::factory()
            ->app()
            ->create(['email' => 'unverified@medplus.ng', 'email_verified_at' => null]);

        $this->postJson('api/v1/partners/apply', $this->application(['contact_email' => strtoupper($taken->email)]))
            ->assertConflict()
            ->assertJsonPath('error.code', 'EMAIL_TAKEN')
            ->assertJsonPath('error.field', 'contact_email');

        $this->postJson('api/v1/partners/apply', $this->application(['state' => 'Atlantis']))
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.field', 'state');
    }

    public function test_applications_are_rate_limited_per_address(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->postJson('api/v1/partners/apply', ['kind' => 'pharmacy'])->assertStatus(400);
        }

        $this->postJson('api/v1/partners/apply', ['kind' => 'pharmacy'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED');
    }

    public function test_the_team_lists_partners_by_kind_status_and_search(): void
    {
        $admin = $this->admin();

        $this->postJson('api/v1/partners/apply', $this->application(['name' => 'Medplus Ikeja']))->assertCreated();
        $this->postJson('api/v1/partners/apply', $this->application(['name' => 'Synlab Lekki', 'kind' => 'laboratory']))->assertCreated();
        $this->approvedPartner(PartnerKind::Pharmacy, ['name' => 'HealthPlus Wuse', 'state' => 'FCT']);

        $this->actingAs($admin)
            ->getJson('api/v1/portal/admin/partners?limit=2')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Medplus Ikeja');

        $this->actingAs($admin)
            ->getJson('api/v1/portal/admin/partners?kind=pharmacy&status=pending')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.name', 'Medplus Ikeja');

        $this->actingAs($admin)
            ->getJson('api/v1/portal/admin/partners?q=fct')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.status', 'approved');
    }

    public function test_approval_creates_the_contacts_account_with_no_password_and_emails_the_link(): void
    {
        Event::fake([PartnerApproved::class]);

        $partner = $this->postJson('api/v1/partners/apply', $this->application(['contact_email' => 'desk@medplus.ng']))->json('data');

        $this->actingAs($this->admin())
            ->postJson("api/v1/portal/admin/partners/{$partner['id']}/review", ['decision' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.dev_set_password_link', null)
            ->assertJsonStructure(['data' => ['reviewed_at']]);

        $account = User::query()
            ->where('email', 'desk@medplus.ng')
            ->firstOrFail();

        $this->assertNull($account->password);
        $this->assertSame([AccountKind::Pharmacy], $account->kinds());
        $this->assertSame($partner['id'], $account->organisation->uuid);

        Event::assertDispatched(PartnerApproved::class);

        $url = null;

        Notification::assertSentTo($account, PortalPasswordLink::class, function (PortalPasswordLink $mail) use (&$url) {
            $url = $mail->url;

            return $mail->welcome;
        });

        $this->postJson('api/v1/portal/auth/set-password', ['token' => str($url)->after('token=')->toString(), 'password' => 'Password1'])
            ->assertOk()
            ->assertJsonPath('data.account.organisation_id', $partner['id'])
            ->assertJsonPath('data.account.organisation_name', 'Medplus Ikeja')
            ->assertJsonPath('data.account.kinds', ['pharmacy']);
    }

    public function test_decisions_must_fit_the_status(): void
    {
        $admin = $this->admin();
        $pending = $this->postJson('api/v1/partners/apply', $this->application())->json('data.id');

        $this->actingAs($admin)
            ->postJson("api/v1/portal/admin/partners/{$pending}/review", ['decision' => 'suspended', 'notes' => 'Licence lapsed in October.'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'PARTNER_NOT_APPROVED');

        $this->actingAs($admin)
            ->postJson("api/v1/portal/admin/partners/{$pending}/review", ['decision' => 'reinstated'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'PARTNER_NOT_SUSPENDED');

        [$approved] = $this->approvedPartner();

        $this->actingAs($admin)
            ->postJson("api/v1/portal/admin/partners/{$approved->uuid}/review", ['decision' => 'approved'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'PARTNER_NOT_PENDING');

        $this->actingAs($admin)
            ->postJson("api/v1/portal/admin/partners/{$approved->uuid}/review", ['decision' => 'suspended', 'notes' => 'short'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'notes');

        $this->actingAs($admin)
            ->postJson('api/v1/portal/admin/partners/0190a0a0-0000-7000-8000-000000000000/review', ['decision' => 'approved'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'PARTNER_NOT_FOUND');
    }

    public function test_approval_links_a_verified_account_and_tells_it(): void
    {
        $patient = User::factory()
            ->app()
            ->create(['email' => 'desk@medplus.ng', 'email_verified_at' => now()]);

        $partner = $this->postJson('api/v1/partners/apply', $this->application(['contact_email' => 'desk@medplus.ng']))->json('data.id');

        $this->actingAs($this->admin())
            ->postJson("api/v1/portal/admin/partners/{$partner}/review", ['decision' => 'approved'])
            ->assertOk();

        $patient->refresh();
        $this->assertSame([AccountKind::Patient, AccountKind::Pharmacy], $patient->kinds());
        $this->assertSame($patient->id, Organisation::query()->where('uuid', $partner)->value('owner_id'));
        Notification::assertSentTo($patient, PortalAccessGranted::class);
    }

    public function test_approval_is_refused_when_the_contacts_email_became_an_unverified_account_meanwhile(): void
    {
        $partner = $this->postJson('api/v1/partners/apply', $this->application(['contact_email' => 'desk@medplus.ng']))->json('data.id');

        User::factory()
            ->app()
            ->create(['email' => 'desk@medplus.ng', 'email_verified_at' => null]);

        $this->actingAs($this->admin())
            ->postJson("api/v1/portal/admin/partners/{$partner}/review", ['decision' => 'approved'])
            ->assertConflict()
            ->assertJsonPath('error.code', 'EMAIL_TAKEN');

        $this->assertSame('pending', Organisation::query()->where('uuid', $partner)->first()->status->value);
    }

    public function test_suspension_tells_the_partner_why_and_reinstatement_brings_it_back(): void
    {
        Event::fake([PartnerSuspended::class, PartnerReinstated::class]);

        $admin = $this->admin();
        [$partner, $account] = $this->approvedPartner();

        $this->actingAs($admin)
            ->postJson("api/v1/portal/admin/partners/{$partner->uuid}/review", ['decision' => 'suspended', 'notes' => 'Licence lapsed in October.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended')
            ->assertJsonPath('data.notes', 'Licence lapsed in October.');

        Event::assertDispatched(PartnerSuspended::class);
        Notification::assertSentTo($account, PartnerStatusChanged::class, fn(PartnerStatusChanged $mail) => $mail->notes === 'Licence lapsed in October.');

        $this->actingAs($admin)
            ->postJson("api/v1/portal/admin/partners/{$partner->uuid}/review", ['decision' => 'reinstated'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.notes', null);

        Event::assertDispatched(PartnerReinstated::class);
    }

    public function test_only_the_team_reviews_partners(): void
    {
        [, $pharmacy] = $this->approvedPartner();

        $this->getJson('api/v1/portal/admin/partners')->assertUnauthorized();

        $this->actingAs($pharmacy)
            ->getJson('api/v1/portal/admin/partners')
            ->assertForbidden();

        $this->actingAs(User::factory()->app(AccountKind::Patient)->create())
            ->postJson('api/v1/portal/admin/partners/0190a0a0-0000-7000-8000-000000000000/review', ['decision' => 'approved'])
            ->assertForbidden();
    }
}
