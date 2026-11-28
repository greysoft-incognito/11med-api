<?php

namespace Tests\Feature\Doctors;

use App\Enums\AccountKind;
use App\Enums\NotificationCategory;
use App\Events\Doctors\DoctorDocumentReviewed;
use App\Events\Doctors\DoctorReviewed;
use App\Models\User;
use App\Services\Notifications\Notifier;
use App\Services\Notifications\SilentNotifier;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DoctorReviewTest extends TestCase
{
    use MakesDoctors;

    public function test_the_team_lists_submitted_applications_oldest_first(): void
    {
        $admin = $this->admin();
        $first = $this->submittedDoctor();
        $this->travel(1)->hours();
        $second = $this->submittedDoctor();
        $draft = $this->completeDoctor();

        $this->actingAs($admin)
            ->getJson('api/v1/portal/admin/doctors?limit=1')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.0.user_id', $first->uuid)
            ->assertJsonPath('data.items.0.phone', $first->phone)
            ->assertJsonPath('data.items.0.documents.0.status', 'uploaded')
            ->assertJsonPath('data.items.0.documents.3.url', null);

        $cursor = $this->actingAs($admin)
            ->getJson('api/v1/portal/admin/doctors?limit=1')
            ->json('data.next_cursor');

        $this->actingAs($admin)
            ->getJson('api/v1/portal/admin/doctors?limit=1&cursor=' . urlencode($cursor))
            ->assertJsonPath('data.items.0.user_id', $second->uuid)
            ->assertJsonPath('data.next_cursor', null);

        $this->actingAs($admin)
            ->getJson('api/v1/portal/admin/doctors?status=approved')
            ->assertJsonPath('data.total', 0);

        $this->assertNotNull($draft);
    }

    public function test_the_list_searches_name_mdcn_number_and_phone(): void
    {
        $admin = $this->admin();
        $doctor = $this->submittedDoctor();
        $this->submittedDoctor();
        $doctor->doctorProfile()->update(['mdcn_number' => 'MDCN/77777']);

        $terms = [
            '77777',
            mb_strtolower($doctor->full_name),
            '+234' . substr($doctor->phone, 1),
        ];

        foreach ($terms as $term) {
            $this->actingAs($admin)
                ->getJson('api/v1/portal/admin/doctors?q=' . urlencode($term))
                ->assertJsonPath('data.total', 1)
                ->assertJsonPath('data.items.0.user_id', $doctor->uuid);
        }
    }

    public function test_the_detail_carries_signed_document_links(): void
    {
        $admin = $this->admin();
        $doctor = $this->submittedDoctor();

        $url = $this->actingAs($admin)
            ->getJson("api/v1/portal/admin/doctors/{$doctor->uuid}")
            ->assertOk()
            ->assertJsonPath('data.full_name', $doctor->full_name)
            ->assertJsonPath('data.documents.5.kind', 'bank_details')
            ->assertJsonPath('data.documents.5.url', null)
            ->json('data.documents.0.url');

        $this->assertStringContainsString('signature=', $url);
        $this->get($url)->assertOk();

        $this->actingAs($admin)
            ->getJson('api/v1/portal/admin/doctors/' . $this->user(AccountKind::Patient)->uuid)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'DOCTOR_NOT_FOUND');
    }

    public function test_only_the_team_reviews(): void
    {
        $doctor = $this->submittedDoctor();

        foreach ([AccountKind::Doctor, AccountKind::Patient, AccountKind::Pharmacy, AccountKind::Laboratory] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->getJson('api/v1/portal/admin/doctors')->assertForbidden();
            $this->actingAs($user)->getJson("api/v1/portal/admin/doctors/{$doctor->uuid}")->assertForbidden();
            $this->actingAs($user)
                ->postJson("api/v1/portal/admin/doctors/{$doctor->uuid}/documents/review", ['kind' => 'government_id', 'decision' => 'accepted'])
                ->assertForbidden();
            $this->actingAs($user)
                ->postJson("api/v1/portal/admin/doctors/{$doctor->uuid}/review", ['decision' => 'approved'])
                ->assertForbidden();
        }
    }

    public function test_a_document_is_accepted_or_rejected_with_a_specific_reason(): void
    {
        Event::fake([DoctorDocumentReviewed::class]);

        $admin = $this->admin();
        $doctor = $this->submittedDoctor();
        $route = "api/v1/portal/admin/doctors/{$doctor->uuid}/documents/review";

        $this->actingAs($admin)
            ->postJson($route, ['kind' => 'government_id', 'decision' => 'rejected', 'reason' => 'Too vague'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'reason');

        $this->actingAs($admin)
            ->postJson($route, ['kind' => 'government_id', 'decision' => 'rejected', 'reason' => 'The NIN slip photo is blurred; retake it in daylight.'])
            ->assertOk()
            ->assertJsonPath('data.documents.1.status', 'rejected')
            ->assertJsonPath('data.documents.1.rejection_reason', 'The NIN slip photo is blurred; retake it in daylight.');

        $this->actingAs($admin)
            ->postJson($route, ['kind' => 'government_id', 'decision' => 'accepted', 'reason' => 'ignored'])
            ->assertOk()
            ->assertJsonPath('data.documents.1.status', 'accepted')
            ->assertJsonPath('data.documents.1.rejection_reason', null);

        $this->actingAs($admin)
            ->postJson($route, ['kind' => 'specialist_certificate', 'decision' => 'accepted'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'DOCUMENT_NOT_UPLOADED');

        Event::assertDispatchedTimes(DoctorDocumentReviewed::class, 2);
    }

    public function test_approval_needs_every_required_document_accepted(): void
    {
        $admin = $this->admin();
        $doctor = $this->submittedDoctor();

        $this->actingAs($admin)
            ->postJson("api/v1/portal/admin/doctors/{$doctor->uuid}/documents/review", ['kind' => 'mdcn_certificate', 'decision' => 'accepted'])
            ->assertOk();

        $this->actingAs($admin)
            ->postJson("api/v1/portal/admin/doctors/{$doctor->uuid}/review", ['decision' => 'approved'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'DOCUMENTS_OUTSTANDING')
            ->assertJsonPath('error.meta.kinds', ['government_id', 'degree_certificate', 'passport_photograph', 'bank_details']);
    }

    public function test_the_decisions_follow_the_lifecycle(): void
    {
        Event::fake([DoctorReviewed::class]);

        $admin = $this->admin();
        $doctor = $this->approvedDoctor(['is_available_now' => true]);
        $route = "api/v1/portal/admin/doctors/{$doctor->uuid}/review";
        $notes = 'Complaint received; on hold pending review.';

        $refusals = [
            'DOCTOR_APPROVED' => ['decision' => 'rejected', 'notes' => $notes],
            'DOCTOR_NOT_SUSPENDED' => ['decision' => 'reinstated'],
        ];

        foreach ($refusals as $code => $body) {
            $this->actingAs($admin)
                ->postJson($route, $body)
                ->assertUnprocessable()
                ->assertJsonPath('error.code', $code);
        }

        $this->actingAs($admin)
            ->postJson($route, ['decision' => 'suspended'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'notes');

        $this->actingAs($admin)
            ->postJson($route, ['decision' => 'suspended', 'notes' => $notes])
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'suspended')
            ->assertJsonPath('data.verification_notes', $notes);

        $this->assertFalse($doctor->doctorProfile()->first()->is_available_now);

        $this->actingAs($admin)
            ->postJson($route, ['decision' => 'approved'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'DOCTOR_SUSPENDED');

        $this->actingAs($admin)
            ->postJson($route, ['decision' => 'suspended', 'notes' => $notes])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'DOCTOR_NOT_APPROVED');

        $this->actingAs($admin)
            ->postJson($route, ['decision' => 'reinstated'])
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'approved')
            ->assertJsonPath('data.verification_notes', null);

        Event::assertDispatched(DoctorReviewed::class, fn(DoctorReviewed $event) => $event->previous->value === 'suspended');
    }

    public function test_the_doctor_is_told_of_each_decision_and_each_rejected_document(): void
    {
        $sent = new class extends SilentNotifier
        {
            /** @var list<array{int, string, string}> */
            public array $sent = [];

            public function send(User $to, NotificationCategory $category, string $title, string $body, array $data = []): ?string
            {
                $this->sent[] = [$to->id, $category->value, $title];

                return null;
            }
        };

        $this->app->instance(Notifier::class, $sent);

        $doctor = $this->approvedDoctor();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson("api/v1/portal/admin/doctors/{$doctor->uuid}/documents/review", ['kind' => 'government_id', 'decision' => 'rejected', 'reason' => 'The NIN slip has expired; upload a current one.'])
            ->assertOk();

        $this->actingAs($admin)
            ->postJson("api/v1/portal/admin/doctors/{$doctor->uuid}/review", ['decision' => 'suspended', 'notes' => 'Identity document has expired.'])
            ->assertOk();

        $this->actingAs($admin)
            ->postJson("api/v1/portal/admin/doctors/{$doctor->uuid}/review", ['decision' => 'reinstated'])
            ->assertOk();

        $this->assertSame([
            [$doctor->id, 'account', __('notifications.doctor_review.approved.title')],
            [$doctor->id, 'account', __('notifications.doctor_document_rejected.title')],
            [$doctor->id, 'account', __('notifications.doctor_review.suspended.title')],
            [$doctor->id, 'account', __('notifications.doctor_review.reinstated.title')],
        ], $sent->sent);
    }
}
