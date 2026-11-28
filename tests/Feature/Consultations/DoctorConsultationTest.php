<?php

namespace Tests\Feature\Consultations;

use App\Enums\NotificationCategory;
use App\Models\Consultation;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DoctorConsultationTest extends TestCase
{
    use MakesConsultations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeSeams();
    }

    public function test_the_doctor_accepts_starts_and_ends_a_session(): void
    {
        $patient = $this->patient();
        $doctor = $this->availableDoctor();
        $id = $this->book($patient, $doctor)['id'];

        $this->pay($id);

        $accepted = $this->doctorDoes($doctor, $id, 'accept');

        $this->assertSame('confirmed', $accepted['status']);
        $this->assertCount(1, $this->notifier->to($patient));

        $this->assertSame('in_progress', $this->doctorDoes($doctor, $id, 'start')['status']);

        $ended = $this->doctorDoes($doctor, $id, 'end');

        $this->assertSame('completed', $ended['status']);
        $this->assertNotNull($ended['ended_at']);
    }

    public function test_each_step_refuses_the_wrong_state(): void
    {
        $patient = $this->patient();
        $doctor = $this->availableDoctor();
        $id = $this->book($patient, $doctor)['id'];

        $this->pay($id);

        $this->actingAs($doctor)->postJson("api/v1/doctor/consultations/{$id}/start")->assertUnprocessable()->assertJsonPath('error.code', 'NOT_CONFIRMED');
        $this->actingAs($doctor)->postJson("api/v1/doctor/consultations/{$id}/end")->assertUnprocessable()->assertJsonPath('error.code', 'NOT_ACTIVE');

        $this->doctorDoes($doctor, $id, 'accept');

        $this->actingAs($doctor)->postJson("api/v1/doctor/consultations/{$id}/accept")->assertUnprocessable()->assertJsonPath('error.code', 'NOT_PENDING');
        $this->actingAs($doctor)->postJson("api/v1/doctor/consultations/{$id}/decline")->assertUnprocessable()->assertJsonPath('error.code', 'NOT_PENDING');
    }

    public function test_declining_refunds_the_patient_in_full(): void
    {
        $patient = $this->patient();
        $doctor = $this->availableDoctor();
        $id = $this->book($patient, $doctor)['id'];

        $this->pay($id);

        $this->assertSame('cancelled', $this->doctorDoes($doctor, $id, 'decline')['status']);
        $this->assertSame([$id], $this->payments->refunded);
        $this->assertSame(NotificationCategory::Consultation, $this->notifier->to($patient)[0]['category']);
    }

    public function test_another_doctors_or_an_unpaid_consultation_is_not_found(): void
    {
        $patient = $this->patient();
        $doctor = $this->availableDoctor();
        $id = $this->book($patient, $doctor)['id'];

        $this->actingAs($doctor)
            ->postJson("api/v1/doctor/consultations/{$id}/accept")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'CONSULTATION_NOT_FOUND');

        $this->pay($id);

        $this->actingAs($this->availableDoctor())
            ->postJson("api/v1/doctor/consultations/{$id}/accept")
            ->assertNotFound();
    }

    public function test_a_scheduled_session_opens_five_minutes_early_and_closes_to_cancelling_an_hour_before(): void
    {
        CarbonImmutable::setTestNow('2026-11-23T07:00:00Z');

        $patient = $this->patient();
        $doctor = $this->approvedDoctor(['availability' => [['day' => 1, 'start' => '09:00', 'end' => '17:00']], 'session_minutes' => 30]);
        $id = $this->book($patient, $doctor, ['scheduled_for' => '2026-11-23T10:00:00.000Z'])['id'];

        $this->pay($id);
        $this->doctorDoes($doctor, $id, 'accept');

        $this->actingAs($doctor)->postJson("api/v1/doctor/consultations/{$id}/start")->assertUnprocessable()->assertJsonPath('error.code', 'NOT_YET');

        CarbonImmutable::setTestNow('2026-11-23T09:30:00Z');

        $this->actingAs($patient)->postJson("api/v1/consultations/{$id}/cancel")->assertUnprocessable()->assertJsonPath('error.code', 'TOO_LATE_TO_CANCEL');

        CarbonImmutable::setTestNow('2026-11-23T09:56:00Z');

        $this->assertSame('in_progress', $this->doctorDoes($doctor, $id, 'start')['status']);
    }

    public function test_a_request_not_accepted_in_five_minutes_expires_and_is_refunded(): void
    {
        $patient = $this->patient();
        $doctor = $this->availableDoctor();
        $id = $this->book($patient, $doctor)['id'];

        $this->pay($id);
        $this->artisan('consultations:settle')->assertSuccessful();

        $this->assertSame('pending', Consultation::query()->where('uuid', $id)->value('status')->value);

        $this->travel(6)->minutes();
        $this->artisan('consultations:settle')->assertSuccessful();

        $this->actingAs($patient)
            ->getJson("api/v1/consultations/{$id}")
            ->assertJsonPath('data.status', 'expired');

        $this->assertSame([$id], $this->payments->refunded);

        $told = $this->notifier->to($doctor);

        $this->assertSame('A consultation request expired', end($told)['title']);
        $this->assertSame($id, end($told)['data']['target_id']);
    }

    public function test_an_accepted_session_not_joined_in_ten_minutes_expires_and_is_refunded(): void
    {
        $patient = $this->patient();
        $doctor = $this->availableDoctor();
        $id = $this->book($patient, $doctor)['id'];

        $this->pay($id);
        $this->doctorDoes($doctor, $id, 'accept');

        $this->travel(11)->minutes();
        $this->artisan('consultations:settle')->assertSuccessful();

        $this->assertSame('expired', Consultation::query()->where('uuid', $id)->value('status')->value);
        $this->assertSame([$id], $this->payments->refunded);
    }

    public function test_an_unpaid_request_expires_and_its_checkout_is_released(): void
    {
        $id = $this->book($this->patient(), $this->availableDoctor())['id'];

        $this->travel(6)->minutes();
        $this->artisan('consultations:settle')->assertSuccessful();

        $this->assertSame('expired', Consultation::query()->where('uuid', $id)->value('status')->value);
        $this->assertSame([$id], $this->payments->refunded);
    }

    public function test_a_payment_landing_after_expiry_is_refunded(): void
    {
        $patient = $this->patient();
        $doctor = $this->availableDoctor();
        $id = $this->book($patient, $doctor)['id'];

        $this->travel(6)->minutes();
        $this->artisan('consultations:settle');
        $this->pay($id);

        $this->assertSame([$id, $id], $this->payments->refunded);
        $this->assertSame([], $this->notifier->to($doctor));
    }

    public function test_an_open_chat_closes_at_the_end_of_its_window(): void
    {
        $patient = $this->patient();
        $doctor = $this->availableDoctor();
        $id = $this->openSession($patient, $doctor);
        $hours = Consultation::query()->where('uuid', $id)->value('length_minutes') / 60;

        $this->travel($hours)->hours();
        $this->travel(1)->minutes();
        $this->artisan('consultations:settle')->assertSuccessful();

        $this->assertSame('completed', Consultation::query()->where('uuid', $id)->value('status')->value);
    }

    public function test_the_inbox_puts_pending_requests_first(): void
    {
        $doctor = $this->availableDoctor();
        $first = $this->book($this->patient(), $doctor)['id'];
        $second = $this->book($this->patient(), $doctor)['id'];

        $this->pay($first);
        $this->pay($second);
        $this->doctorDoes($doctor, $first, 'accept');

        $this->actingAs($doctor)
            ->getJson('api/v1/doctor/consultations?limit=1')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.0.id', $second);
    }

    public function test_the_patient_profile_is_withheld_unless_shared(): void
    {
        $doctor = $this->availableDoctor();
        $id = $this->book($this->patient(), $doctor, ['profile_shared' => false])['id'];

        $this->pay($id);

        $this->actingAs($doctor)
            ->getJson('api/v1/doctor/consultations')
            ->assertJsonPath('data.items.0.id', $id)
            ->assertJsonPath('data.items.0.patient_profile', null);
    }

    public function test_suspending_a_doctor_cancels_and_refunds_what_has_not_begun(): void
    {
        $doctor = $this->availableDoctor();
        $waiting = $this->book($this->patient(), $doctor)['id'];
        $patient = $this->patient();
        $started = $this->openSession($patient, $doctor);

        $this->pay($waiting);

        $this->actingAs($this->admin())
            ->postJson("api/v1/portal/admin/doctors/{$doctor->uuid}/review", ['decision' => 'suspended', 'notes' => 'Licence under investigation by the council.'])
            ->assertOk();

        $this->assertSame('cancelled', Consultation::query()->where('uuid', $waiting)->value('status')->value);
        $this->assertSame('in_progress', Consultation::query()->where('uuid', $started)->value('status')->value);
        $this->assertSame([$waiting], $this->payments->refunded);
    }
}
