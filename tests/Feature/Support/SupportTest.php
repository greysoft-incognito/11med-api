<?php

namespace Tests\Feature\Support;

use App\Enums\AdminPermission;
use App\Enums\MediaPurpose;
use App\Enums\NotificationCategory;
use App\Enums\PartnerKind;
use App\Models\AppNotification;
use App\Models\Order;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\MakesPartners;
use Tests\Concerns\MakesPharmacyOrders;
use Tests\TestCase;

class SupportTest extends TestCase
{
    use MakesPartners;
    use MakesPharmacyOrders;

    private User $patient;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Notification::fake();

        $this->patient = $this->patient('Lagos');
        $this->agent = $this->admin();
        $this->agent->givePermissionTo($this->permissions(AdminPermission::SupportView, AdminPermission::SupportReply));
    }

    public function test_a_patient_opens_a_ticket_and_reads_it_back(): void
    {
        $attachment = $this->attachment($this->patient);

        $id = $this->actingAs($this->patient)
            ->postJson('api/v1/support/tickets', [
                'category' => 'payment',
                'subject' => 'Charged twice',
                'message' => 'My card was charged twice for one order.',
                'attachment_url' => $attachment,
            ])
            ->assertCreated()
            ->assertJsonPath('data.category', 'payment')
            ->assertJsonPath('data.subject', 'Charged twice')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.link', null)
            ->assertJsonPath('data.closed_at', null)
            ->json('data.id');

        $this->actingAs($this->patient)
            ->getJson('api/v1/support/tickets')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.id', $id);

        $this->actingAs($this->patient)
            ->getJson("api/v1/support/tickets/{$id}/messages")
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.from', 'requester')
            ->assertJsonPath('data.items.0.body', 'My card was charged twice for one order.')
            ->assertJsonPath('data.items.0.attachment_name', 'statement.pdf')
            ->assertJsonPath('data.items.0.attachment_content_type', 'application/pdf');

        $stored = DB::table('support_messages')->value('body');

        $this->assertStringNotContainsString('charged twice', (string) $stored);
    }

    public function test_a_ticket_may_name_the_requesters_own_order(): void
    {
        [$pharmacy, $owner] = $this->approvedPartner(PartnerKind::Pharmacy);
        $payments = $this->fakePayments();
        $this->addToCart($this->patient, $this->drug($pharmacy));
        $orderId = $this->checkout($this->patient)[0]['id'];
        $payments->settle(Order::query()->where('uuid', $orderId)->firstOrFail());

        $this->actingAs($this->patient)
            ->postJson('api/v1/support/tickets', $this->ticket(['category' => 'order', 'link' => ['kind' => 'order', 'id' => $orderId]]))
            ->assertCreated()
            ->assertJsonPath('data.link.kind', 'order')
            ->assertJsonPath('data.link.id', $orderId);

        $this->actingAs($owner)
            ->postJson('api/v1/support/tickets', $this->ticket(['link' => ['kind' => 'order', 'id' => $orderId]]))
            ->assertCreated();

        $this->actingAs($this->patient('Lagos'))
            ->postJson('api/v1/support/tickets', $this->ticket(['link' => ['kind' => 'order', 'id' => $orderId]]))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SUPPORT_LINK_NOT_FOUND')
            ->assertJsonPath('error.field', 'link.id');

        $this->actingAs($this->patient)
            ->postJson('api/v1/support/tickets', $this->ticket(['link' => ['kind' => 'consultation', 'id' => $orderId]]))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SUPPORT_LINK_NOT_FOUND');
    }

    public function test_a_ticket_is_validated(): void
    {
        $this->actingAs($this->patient)
            ->postJson('api/v1/support/tickets', $this->ticket(['category' => 'billing']))
            ->assertBadRequest()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.field', 'category');

        $this->actingAs($this->patient)
            ->postJson('api/v1/support/tickets', $this->ticket(['message' => '']))
            ->assertBadRequest()
            ->assertJsonPath('error.field', 'message');

        $this->actingAs($this->patient)
            ->postJson('api/v1/support/tickets', $this->ticket(['attachment_url' => 'https://example.com/elsewhere.pdf']))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'MEDIA_NOT_FOUND')
            ->assertJsonPath('error.field', 'attachment_url');
    }

    public function test_another_accounts_ticket_is_not_found(): void
    {
        $id = $this->open($this->patient);
        $other = $this->patient('Lagos');

        $this->actingAs($other)
            ->getJson("api/v1/support/tickets/{$id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'TICKET_NOT_FOUND');

        $this->actingAs($other)
            ->postJson("api/v1/support/tickets/{$id}/messages", ['message' => 'Hello'])
            ->assertNotFound();
    }

    public function test_the_team_assigns_replies_and_the_requester_is_told(): void
    {
        $id = $this->open($this->patient);

        $this->actingAs($this->agent)
            ->postJson("api/v1/portal/admin/support/tickets/{$id}/assign", ['assignee_id' => $this->agent->uuid])
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $this->agent->uuid)
            ->assertJsonPath('data.requester.id', $this->patient->uuid)
            ->assertJsonPath('data.requester.kinds', ['patient']);

        $this->actingAs($this->agent)
            ->postJson("api/v1/portal/admin/support/tickets/{$id}/messages", ['message' => 'We have refunded the second charge.'])
            ->assertCreated()
            ->assertJsonPath('data.from', 'team')
            ->assertJsonPath('data.author_name', $this->agent->full_name);

        $this->actingAs($this->patient)
            ->getJson("api/v1/support/tickets/{$id}")
            ->assertJsonPath('data.status', 'answered');

        $notice = AppNotification::query()
            ->where('user_id', $this->patient->id)
            ->where('category', NotificationCategory::Support)
            ->sole();

        $this->assertSame($id, $notice->target_id);
        $this->assertStringNotContainsString('refunded', $notice->body);

        $this->actingAs($this->patient)
            ->postJson("api/v1/support/tickets/{$id}/messages", ['message' => 'Thank you, it arrived.'])
            ->assertCreated()
            ->assertJsonPath('data.from', 'requester');

        $this->actingAs($this->agent)
            ->getJson("api/v1/portal/admin/support/tickets/{$id}")
            ->assertJsonPath('data.status', 'open');

        $this->actingAs($this->agent)
            ->getJson("api/v1/portal/admin/support/tickets/{$id}/messages")
            ->assertJsonPath('data.total', 3);
    }

    public function test_a_closed_ticket_takes_no_more_messages(): void
    {
        $id = $this->open($this->patient);

        $this->actingAs($this->agent)
            ->postJson("api/v1/portal/admin/support/tickets/{$id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->actingAs($this->patient)
            ->postJson("api/v1/support/tickets/{$id}/messages", ['message' => 'One more thing'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'TICKET_CLOSED');

        $this->actingAs($this->agent)
            ->postJson("api/v1/portal/admin/support/tickets/{$id}/messages", ['message' => 'Reopening'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'TICKET_CLOSED');

        $this->actingAs($this->agent)
            ->postJson("api/v1/portal/admin/support/tickets/{$id}/close")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'TICKET_CLOSED');
    }

    public function test_the_queue_pages_and_filters(): void
    {
        [, $owner] = $this->approvedPartner(PartnerKind::Laboratory, ['name' => 'Clina Lancet']);
        $first = $this->open($this->patient, ['category' => 'payment', 'subject' => 'Charged twice']);
        $this->travel(1)->minutes();
        $second = $this->open($owner, ['category' => 'technical', 'subject' => 'Upload fails']);

        $this->actingAs($this->agent)
            ->postJson("api/v1/portal/admin/support/tickets/{$first}/assign", ['assignee_id' => $this->agent->uuid])
            ->assertOk();

        $this->actingAs($this->agent)
            ->getJson('api/v1/portal/admin/support/tickets?limit=1')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.0.id', $second);

        $this->actingAs($this->agent)
            ->getJson('api/v1/portal/admin/support/tickets?category=technical')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.id', $second)
            ->assertJsonPath('data.items.0.requester.organisation_name', 'Clina Lancet');

        $this->actingAs($this->agent)
            ->getJson('api/v1/portal/admin/support/tickets?assigned=unassigned')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.id', $second);

        $this->actingAs($this->agent)
            ->getJson('api/v1/portal/admin/support/tickets?assigned=me&status=open')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.id', $first);

        $this->actingAs($this->agent)
            ->getJson('api/v1/portal/admin/support/tickets?q=upload')
            ->assertJsonPath('data.total', 1);

        $this->actingAs($this->agent)
            ->getJson('api/v1/portal/admin/support/tickets?status=lost')
            ->assertBadRequest()
            ->assertJsonPath('error.field', 'status');
    }

    public function test_assigning_needs_a_team_member_who_can_reply(): void
    {
        $id = $this->open($this->patient);

        $this->actingAs($this->agent)
            ->postJson("api/v1/portal/admin/support/tickets/{$id}/assign", ['assignee_id' => $this->patient->uuid])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ASSIGNEE_NOT_SUPPORT')
            ->assertJsonPath('error.field', 'assignee_id');

        $this->actingAs($this->agent)
            ->postJson("api/v1/portal/admin/support/tickets/{$id}/assign", ['assignee_id' => null])
            ->assertOk()
            ->assertJsonPath('data.assignee', null);
    }

    public function test_the_queue_needs_the_support_permissions(): void
    {
        $id = $this->open($this->patient);

        $viewer = User::factory()
            ->staff()
            ->create();

        $viewer->givePermissionTo($this->permissions(AdminPermission::SupportView));

        $noPermission = User::factory()
            ->staff()
            ->create();

        $this->actingAs($viewer)
            ->getJson('api/v1/portal/admin/support/tickets')
            ->assertOk();

        $this->actingAs($viewer)
            ->postJson("api/v1/portal/admin/support/tickets/{$id}/close")
            ->assertForbidden();

        $this->actingAs($noPermission)
            ->getJson('api/v1/portal/admin/support/tickets')
            ->assertForbidden();

        $this->actingAs($this->patient)
            ->getJson('api/v1/portal/admin/support/tickets')
            ->assertForbidden();

        $this->actingAs($this->agent)
            ->getJson('api/v1/support/tickets')
            ->assertForbidden();
    }

    public function test_erasing_the_requester_removes_their_tickets(): void
    {
        $this->open($this->patient);

        $this->actingAs($this->patient)
            ->deleteJson('api/v1/auth/account', ['password' => 'Password1'])
            ->assertOk();

        $this->assertSame(0, DB::table('support_tickets')->count());
        $this->assertSame(0, SupportMessage::withTrashed()->count());
    }

    /**
     * @return list<Permission>
     */
    private function permissions(AdminPermission ...$permissions): array
    {
        return array_map(fn(AdminPermission $permission) => Permission::findOrCreate($permission->value, 'web'), $permissions);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function ticket(array $overrides = []): array
    {
        return [
            'category' => 'other',
            'subject' => 'A question',
            'message' => 'How do I change my delivery address?',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function open(User $requester, array $overrides = []): string
    {
        return $this->actingAs($requester)
            ->postJson('api/v1/support/tickets', $this->ticket($overrides))
            ->assertCreated()
            ->json('data.id');
    }

    private function attachment(User $owner): string
    {
        $library = app(MediaLibrary::class);
        $media = $library->upload($owner, MediaPurpose::SupportAttachment, UploadedFile::fake()->createWithContent('statement.pdf', "%PDF-1.4\n%%EOF\n"), []);

        return $library->url($media);
    }
}
