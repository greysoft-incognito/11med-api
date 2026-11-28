<?php

namespace Tests\Feature\Operations;

use App\Enums\AdminPermission;
use App\Enums\LabBookingStatus;
use App\Models\LabBooking;
use App\Models\Laboratory;
use App\Models\User;
use App\Services\Audit\AuditLog;
use Tests\Concerns\MakesAdmins;
use Tests\Concerns\MakesLaboratories;
use Tests\Fakes\RecordingAuditLog;
use Tests\TestCase;

class AdminLabBookingTest extends TestCase
{
    use MakesAdmins;
    use MakesLaboratories;

    private RecordingAuditLog $audit;

    private User $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-01T09:00:00Z');
        $this->fakeLabSeams();

        $this->audit = new RecordingAuditLog;
        $this->app->instance(AuditLog::class, $this->audit);

        $this->team = $this->teamMember();
    }

    public function test_bookings_are_listed_without_results_or_notes(): void
    {
        [$laboratory] = $this->laboratory(['FBC' => 300000], ['name' => 'Synlab Yaba']);
        $patient = $this->labPatient();
        $booking = $this->book($laboratory, $patient);

        $this->actingAs($this->team)
            ->getJson('api/v1/portal/admin/lab-bookings')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.id', $booking->uuid)
            ->assertJsonPath('data.items.0.laboratory_name', 'Synlab Yaba')
            ->assertJsonPath('data.items.0.patient_id', $patient->uuid)
            ->assertJsonPath('data.items.0.tests.0.test_code', 'FBC')
            ->assertJsonMissingPath('data.items.0.results_url')
            ->assertJsonMissingPath('data.items.0.request_notes');

        foreach ([$booking->reference, 'synlab', $patient->phone] as $q) {
            $this->actingAs($this->team)
                ->getJson('api/v1/portal/admin/lab-bookings?q=' . urlencode($q))
                ->assertJsonPath('data.total', 1);
        }

        $this->actingAs($this->team)->getJson('api/v1/portal/admin/lab-bookings?status=open')->assertJsonPath('data.total', 1);
        $this->actingAs($this->team)->getJson('api/v1/portal/admin/lab-bookings?status=results_ready')->assertJsonPath('data.total', 0);
    }

    public function test_the_team_cancels_a_booked_one_and_the_patient_is_refunded_and_told(): void
    {
        [$laboratory] = $this->laboratory(['FBC' => 300000]);
        $patient = $this->labPatient();
        $booking = $this->book($laboratory, $patient);

        $this->actingAs($this->team)
            ->postJson("api/v1/portal/admin/lab-bookings/{$booking->uuid}/cancel", ['reason' => 'The laboratory is closed this week.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation', ['by' => 'team', 'reason' => 'The laboratory is closed this week.']);

        $this->assertContains($booking->uuid, array_column($this->payments->refunds, 'target'));
        $this->assertSame(['lab_booking.cancelled'], $this->audit->actions());
        $this->assertSame('The 11med team cancelled your booking', end($this->notifier->sent)['title']);

        $this->actingAs($this->team)
            ->postJson("api/v1/portal/admin/lab-bookings/{$booking->uuid}/cancel", ['reason' => 'The laboratory is closed this week.'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'BOOKING_CANCELLED');
    }

    public function test_a_booking_past_collection_is_not_cancelled(): void
    {
        [$laboratory] = $this->laboratory(['FBC' => 300000]);
        $booking = $this->book($laboratory, $this->labPatient());
        $booking->forceFill(['status' => LabBookingStatus::SampleCollected])->save();

        $this->actingAs($this->team)
            ->postJson("api/v1/portal/admin/lab-bookings/{$booking->uuid}/cancel", ['reason' => 'The patient asked us to cancel.'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'BOOKING_STARTED');

        $this->actingAs($this->team)
            ->getJson('api/v1/portal/admin/lab-bookings/0193a0a0-0000-7000-8000-000000000000')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'LAB_BOOKING_NOT_FOUND');
    }

    public function test_permissions_are_refused(): void
    {
        [$laboratory, $account] = $this->laboratory(['FBC' => 300000]);
        $booking = $this->book($laboratory, $this->labPatient());

        $this->actingAs($this->teamMember(AdminPermission::BookingsView))
            ->postJson("api/v1/portal/admin/lab-bookings/{$booking->uuid}/cancel", ['reason' => 'The patient asked us to cancel.'])
            ->assertForbidden();

        $this->actingAs($account)
            ->getJson('api/v1/portal/admin/lab-bookings')
            ->assertForbidden();
    }

    private function book(Laboratory $laboratory, User $patient): LabBooking
    {
        $id = $this->actingAs($patient)
            ->postJson('api/v1/labs/bookings', $this->bookingBody($laboratory, ['FBC']))
            ->assertCreated()
            ->json('data.id');

        $booking = LabBooking::query()->where('uuid', $id)->firstOrFail();
        $this->payments->pay($booking);

        return $booking->refresh();
    }
}
