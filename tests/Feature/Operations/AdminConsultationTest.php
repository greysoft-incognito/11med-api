<?php

namespace Tests\Feature\Operations;

use App\Enums\AdminPermission;
use App\Services\Audit\AuditLog;
use Tests\Concerns\MakesAdmins;
use Tests\Fakes\RecordingAuditLog;
use Tests\Feature\Consultations\MakesConsultations;
use Tests\TestCase;

class AdminConsultationTest extends TestCase
{
    use MakesAdmins;
    use MakesConsultations;

    private RecordingAuditLog $audit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-01T09:00:00Z');
        $this->fakeSeams();

        $this->audit = new RecordingAuditLog;
        $this->app->instance(AuditLog::class, $this->audit);
    }

    public function test_consultations_are_listed_without_the_complaint(): void
    {
        $doctor = $this->availableDoctor();
        $patient = $this->patient();
        $id = $this->book($patient, $doctor)['id'];

        $this->actingAs($this->teamMember())
            ->getJson('api/v1/portal/admin/consultations')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.id', $id)
            ->assertJsonPath('data.items.0.status', 'pending')
            ->assertJsonPath('data.items.0.patient_id', $patient->uuid)
            ->assertJsonPath('data.items.0.doctor_id', $doctor->uuid)
            ->assertJsonMissingPath('data.items.0.complaint');

        $this->actingAs($this->teamMember())
            ->getJson('api/v1/portal/admin/consultations?q=' . urlencode($patient->phone))
            ->assertJsonPath('data.total', 1);

        $this->actingAs($this->teamMember())
            ->getJson('api/v1/portal/admin/consultations?status=completed')
            ->assertJsonPath('data.total', 0);
    }

    public function test_the_team_cancels_a_booking_that_has_not_begun_and_both_sides_hear(): void
    {
        $doctor = $this->availableDoctor();
        $patient = $this->patient();
        $id = $this->book($patient, $doctor)['id'];
        $this->pay($id);
        $this->doctorDoes($doctor, $id, 'accept');

        $this->actingAs($this->teamMember())
            ->postJson("api/v1/portal/admin/consultations/{$id}/cancel", ['reason' => 'The doctor reported a family emergency.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertContains($id, $this->payments->refunded);
        $this->assertSame(['consultation.cancelled'], $this->audit->actions());
        $toPatient = $this->notifier->to($patient);
        $toDoctor = $this->notifier->to($doctor);

        $this->assertSame('Your consultation was cancelled', end($toPatient)['title']);
        $this->assertSame('A consultation was cancelled', end($toDoctor)['title']);

        $this->actingAs($this->teamMember())
            ->postJson("api/v1/portal/admin/consultations/{$id}/cancel", ['reason' => 'The doctor reported a family emergency.'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'CONSULTATION_CLOSED');
    }

    public function test_a_session_under_way_is_not_cancelled(): void
    {
        $doctor = $this->availableDoctor();
        $id = $this->openSession($this->patient(), $doctor);

        $this->actingAs($this->teamMember())
            ->postJson("api/v1/portal/admin/consultations/{$id}/cancel", ['reason' => 'The patient asked us to cancel.'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'CONSULTATION_IN_PROGRESS');
    }

    public function test_permissions_are_refused(): void
    {
        $doctor = $this->availableDoctor();
        $id = $this->book($this->patient(), $doctor)['id'];

        $this->actingAs($this->teamMember(AdminPermission::ConsultationsView))
            ->postJson("api/v1/portal/admin/consultations/{$id}/cancel", ['reason' => 'The patient asked us to cancel.'])
            ->assertForbidden();

        $this->actingAs($doctor)
            ->getJson("api/v1/portal/admin/consultations/{$id}")
            ->assertForbidden();
    }
}
