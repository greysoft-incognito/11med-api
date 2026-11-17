<?php

namespace Tests\Feature\Consultations;

use App\Enums\ConsentContext;
use App\Enums\Role;
use App\Models\ConsentRecord;
use App\Models\Consultation;
use App\Models\DoctorProfile;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use MakesConsultations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeSeams();
    }

    public function test_a_patient_books_a_doctor_available_now_and_pays_at_booking(): void
    {
        $patient = $this->patient();
        $doctor = $this->availableDoctor();

        $booked = $this->book($patient, $doctor, ['channel' => 'card']);

        $this->assertSame('pending', $booked['status']);
        $this->assertSame(500000, $booked['fee_kobo']);
        $this->assertSame($doctor->uuid, $booked['doctor_id']);
        $this->assertFalse($booked['scheduled']);
        $this->assertSame('pending', $booked['payment']['status']);
        $this->assertSame([$booked['id']], $this->payments->initialised);
        $this->assertSame('card', $this->payments->lastChannel?->value);
        $this->assertNotNull($booked['expires_at']);

        $consent = ConsentRecord::query()->where('user_id', $patient->id)->where('context', ConsentContext::ProfileSharing)->first();
        $this->assertNotNull($consent);
    }

    public function test_an_unpaid_request_is_never_shown_to_the_doctor(): void
    {
        $patient = $this->patient();
        $doctor = $this->availableDoctor();
        $id = $this->book($patient, $doctor)['id'];

        $this->actingAs($doctor)
            ->getJson('api/v1/doctor/consultations')
            ->assertOk()
            ->assertJsonPath('data.total', 0);

        $this->pay($id);

        $this->actingAs($doctor)
            ->getJson('api/v1/doctor/consultations')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $id)
            ->assertJsonPath('data.items.0.patient_profile.blood_type', 'O+');

        $this->assertCount(1, $this->notifier->to($doctor));
    }

    public function test_booking_is_refused_with_the_contract_codes(): void
    {
        $patient = $this->patient();
        $busy = $this->approvedDoctor();
        $doctor = $this->availableDoctor();

        DoctorProfile::query()->where('user_id', $doctor->id)->update(['fee_audio_kobo' => null]);

        $this->actingAs($patient)
            ->postJson('api/v1/consultations', ['doctor_id' => '0190d6f0-0000-7000-8000-000000000000', 'session_type' => 'chat', 'complaint' => 'Headache since Monday.', 'scheduled_for' => null, 'profile_shared' => false])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'DOCTOR_NOT_FOUND');

        $this->actingAs($patient)
            ->postJson('api/v1/consultations', ['doctor_id' => $busy->uuid, 'session_type' => 'chat', 'complaint' => 'Headache since Monday.', 'scheduled_for' => null, 'profile_shared' => false])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'DOCTOR_UNAVAILABLE');

        $this->actingAs($patient)
            ->postJson('api/v1/consultations', ['doctor_id' => $doctor->uuid, 'session_type' => 'audio', 'complaint' => 'Headache since Monday.', 'scheduled_for' => null, 'profile_shared' => false])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SESSION_TYPE_UNAVAILABLE');

        $this->actingAs($patient)
            ->postJson('api/v1/consultations', ['doctor_id' => $doctor->uuid, 'session_type' => 'chat', 'complaint' => '', 'scheduled_for' => null, 'profile_shared' => false])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.field', 'complaint');

        $this->actingAs($patient)
            ->postJson('api/v1/consultations', ['doctor_id' => $doctor->uuid, 'session_type' => 'chat', 'complaint' => 'Headache since Monday.', 'scheduled_for' => CarbonImmutable::now()->addDays(30)->toIso8601ZuluString(), 'profile_shared' => false])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SLOT_UNAVAILABLE')
            ->assertJsonPath('error.field', 'scheduled_for');

        $this->actingAs($doctor)
            ->postJson('api/v1/consultations', ['doctor_id' => $doctor->uuid, 'session_type' => 'chat', 'complaint' => 'Headache since Monday.', 'scheduled_for' => null, 'profile_shared' => false])
            ->assertForbidden();
    }

    public function test_a_scheduled_slot_is_held_once(): void
    {
        CarbonImmutable::setTestNow('2026-11-23T07:00:00Z');

        $doctor = $this->approvedDoctor(['availability' => [['day' => 1, 'start' => '09:00', 'end' => '17:00']], 'session_minutes' => 30]);
        $first = $this->patient();
        $second = $this->patient();
        $slot = '2026-11-23T10:00:00.000Z';

        $booked = $this->book($first, $doctor, ['scheduled_for' => $slot]);

        $this->assertTrue($booked['scheduled']);
        $this->assertSame($slot, $booked['scheduled_for']);

        $this->actingAs($second)
            ->postJson('api/v1/consultations', ['doctor_id' => $doctor->uuid, 'session_type' => 'chat', 'complaint' => 'Headache since Monday.', 'scheduled_for' => $slot, 'profile_shared' => false])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SLOT_TAKEN');

        $this->actingAs($second)
            ->getJson("api/v1/doctors/{$doctor->uuid}/slots?date=2026-11-23")
            ->assertOk();
    }

    public function test_a_patient_cancels_before_the_cutoff_and_is_refunded(): void
    {
        $patient = $this->patient();
        $doctor = $this->availableDoctor();
        $id = $this->book($patient, $doctor)['id'];

        $this->pay($id);

        $this->actingAs($patient)
            ->postJson("api/v1/consultations/{$id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame([$id], $this->payments->refunded);

        $this->actingAs($patient)
            ->postJson("api/v1/consultations/{$id}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'CONSULTATION_CLOSED');
    }

    public function test_a_session_in_progress_or_too_close_cannot_be_cancelled(): void
    {
        $patient = $this->patient();
        $doctor = $this->availableDoctor();
        $id = $this->openSession($patient, $doctor);

        $this->actingAs($patient)
            ->postJson("api/v1/consultations/{$id}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'CONSULTATION_IN_PROGRESS');

        $other = $this->patient();
        $next = $this->book($other, $this->availableDoctor())['id'];

        $this->actingAs($other)
            ->postJson("api/v1/consultations/{$next}/cancel")
            ->assertOk();
    }

    public function test_another_patients_consultation_is_not_found(): void
    {
        $doctor = $this->availableDoctor();
        $id = $this->book($this->patient(), $doctor)['id'];

        $this->actingAs($this->patient())
            ->getJson("api/v1/consultations/{$id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'CONSULTATION_NOT_FOUND');
    }

    public function test_the_patient_lists_their_consultations_paged(): void
    {
        $patient = $this->patient();

        $this->book($patient, $this->availableDoctor());
        $this->book($patient, $this->availableDoctor());

        $this->actingAs($patient)
            ->getJson('api/v1/consultations?limit=1')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonStructure(['data' => ['items' => [['id', 'payment', 'expires_at']], 'next_cursor']]);
    }

    public function test_a_follow_up_is_with_the_same_doctor_within_its_window_at_the_follow_up_fee(): void
    {
        $patient = $this->patient();
        $doctor = $this->availableDoctor(['follow_up_fee_kobo' => 200000]);
        $other = $this->availableDoctor();
        $first = $this->openSession($patient, $doctor);

        $this->actingAs($patient)
            ->postJson('api/v1/consultations', ['doctor_id' => $doctor->uuid, 'session_type' => 'chat', 'complaint' => 'Back again.', 'scheduled_for' => null, 'profile_shared' => false, 'follow_up_of' => $first])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'DOCTOR_UNAVAILABLE');

        $this->doctorDoes($doctor, $first, 'end');

        $this->actingAs($patient)
            ->postJson('api/v1/consultations', ['doctor_id' => $other->uuid, 'session_type' => 'chat', 'complaint' => 'Back again.', 'scheduled_for' => null, 'profile_shared' => false, 'follow_up_of' => $first])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'FOLLOW_UP_OTHER_DOCTOR');

        $followUp = $this->book($patient, $doctor, ['follow_up_of' => $first]);

        $this->assertSame(200000, $followUp['fee_kobo']);
        $this->assertSame($first, $followUp['follow_up_of']);

        Consultation::query()->where('uuid', $first)->update(['ended_at' => now()->subDays(15)]);
        Consultation::query()->where('uuid', $followUp['id'])->delete();

        $this->actingAs($patient)
            ->postJson('api/v1/consultations', ['doctor_id' => $doctor->uuid, 'session_type' => 'chat', 'complaint' => 'Back again.', 'scheduled_for' => null, 'profile_shared' => false, 'follow_up_of' => $first])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'FOLLOW_UP_EXPIRED');
    }

    public function test_a_free_consultation_reaches_the_doctor_without_a_payment(): void
    {
        $patient = $this->patient();
        $doctor = $this->availableDoctor(['fee_chat_kobo' => 0]);

        $booked = $this->book($patient, $doctor);

        $this->assertSame([], $this->payments->initialised);
        $this->assertSame('paid', $booked['payment']['status']);

        $this->actingAs($doctor)
            ->getJson('api/v1/doctor/consultations')
            ->assertJsonPath('data.total', 1);
    }

    public function test_other_roles_are_refused(): void
    {
        $this->actingAs($this->user(Role::Pharmacy))
            ->getJson('api/v1/consultations')
            ->assertForbidden();

        $this->actingAs($this->user(Role::Patient))
            ->getJson('api/v1/doctor/consultations')
            ->assertForbidden();
    }
}
