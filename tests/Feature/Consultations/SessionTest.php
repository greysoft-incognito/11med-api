<?php

namespace Tests\Feature\Consultations;

use App\Enums\MediaPurpose;
use App\Models\DoctorProfile;
use App\Models\Medication;
use Database\Seeders\DrugClassSeeder;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use MakesConsultations;

    private const DRUG = [
        'drug_name' => 'Amoxicillin',
        'dosage_strength' => '500mg',
        'dosage_form' => 'capsule',
        'dose_quantity' => 1,
        'dose_unit' => null,
        'times' => ['08:00', '20:00'],
        'duration_days' => 7,
        'special_instructions' => null,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeSeams();
        $this->seed(DrugClassSeeder::class);
    }

    public function test_both_sides_chat_while_the_session_is_open(): void
    {
        $patient = $this->patient();
        $doctor = $this->availableDoctor();
        $id = $this->book($patient, $doctor)['id'];

        $this->actingAs($patient)
            ->postJson("api/v1/consultations/{$id}/messages", ['body' => 'Hello doctor'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'CONSULTATION_NOT_ACTIVE');

        $this->pay($id);
        $this->doctorDoes($doctor, $id, 'accept');
        $this->doctorDoes($doctor, $id, 'start');

        $this->actingAs($patient)
            ->postJson("api/v1/consultations/{$id}/messages", ['body' => 'Hello doctor'])
            ->assertCreated()
            ->assertJsonPath('data.sender', 'patient');

        $this->actingAs($doctor)
            ->postJson("api/v1/doctor/consultations/{$id}/messages", ['body' => 'Good morning'])
            ->assertCreated()
            ->assertJsonPath('data.sender', 'doctor');

        $this->actingAs($patient)
            ->getJson("api/v1/consultations/{$id}/messages")
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.0.body', 'Hello doctor')
            ->assertJsonPath('data.items.1.body', 'Good morning');

        $this->actingAs($patient)
            ->postJson("api/v1/consultations/{$id}/messages", ['body' => '   '])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'body');

        $this->actingAs($this->patient())
            ->getJson("api/v1/consultations/{$id}/messages")
            ->assertNotFound();
    }

    public function test_an_attachment_must_be_the_senders_own_upload(): void
    {
        $patient = $this->patient();
        $doctor = $this->availableDoctor();
        $id = $this->openSession($patient, $doctor);
        $own = $this->upload($patient, MediaPurpose::MessageAttachment);
        $theirs = $this->upload($doctor, MediaPurpose::MessageAttachment);

        $this->actingAs($patient)
            ->postJson("api/v1/consultations/{$id}/messages", ['attachment_url' => $theirs])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'MEDIA_NOT_FOUND');

        $this->actingAs($patient)
            ->postJson("api/v1/consultations/{$id}/messages", ['body' => null, 'attachment_url' => $own])
            ->assertCreated()
            ->assertJsonPath('data.attachment_content_type', 'image/jpeg')
            ->assertJsonPath('data.attachment_name', null);
    }

    public function test_the_doctor_prescribes_and_the_patient_reads_it(): void
    {
        $patient = $this->patient();
        $doctor = $this->availableDoctor();
        $id = $this->openSession($patient, $doctor);

        $this->actingAs($patient)
            ->getJson("api/v1/consultations/{$id}/prescription")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NO_PRESCRIPTION');

        $this->actingAs($doctor)
            ->postJson("api/v1/doctor/consultations/{$id}/prescription", ['diagnosis_summary' => 'Bacterial tonsillitis', 'drugs' => [self::DRUG]])
            ->assertCreated()
            ->assertJsonPath('data.mdcn_number', 'MDCN/12345')
            ->assertJsonPath('data.added_to_schedule', false);

        $this->actingAs($doctor)
            ->postJson("api/v1/doctor/consultations/{$id}/prescription", ['diagnosis_summary' => 'Tonsillitis', 'drugs' => [['drug_name' => 'Paracetamol'] + self::DRUG]])
            ->assertCreated();

        $this->actingAs($patient)
            ->getJson("api/v1/consultations/{$id}/prescription")
            ->assertOk()
            ->assertJsonPath('data.diagnosis_summary', 'Tonsillitis')
            ->assertJsonPath('data.drugs.0.drug_name', 'Paracetamol');

        $this->assertCount(2, array_filter($this->notifier->to($patient), fn(array $sent) => $sent['category']->value === 'prescription'));
    }

    public function test_the_patient_adds_a_prescription_to_their_schedule_once(): void
    {
        $patient = $this->patient();
        $doctor = $this->availableDoctor();
        $id = $this->openSession($patient, $doctor);

        $this->actingAs($patient)
            ->postJson("api/v1/consultations/{$id}/prescription/apply")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NO_PRESCRIPTION');

        $prescription = $this->actingAs($doctor)
            ->postJson("api/v1/doctor/consultations/{$id}/prescription", ['diagnosis_summary' => 'Bacterial tonsillitis', 'drugs' => [self::DRUG]])
            ->json('data.id');

        $first = $this->actingAs($patient)
            ->postJson("api/v1/consultations/{$id}/prescription/apply")
            ->assertOk()
            ->assertJsonPath('data.0.drug_name', 'Amoxicillin')
            ->assertJsonPath('data.0.prescription_id', $prescription)
            ->assertJsonPath('data.0.prescribed_by_doctor_id', $doctor->uuid)
            ->assertJsonPath('data.0.condition_being_treated', 'Bacterial tonsillitis')
            ->json('data');

        $again = $this->actingAs($patient)
            ->postJson("api/v1/consultations/{$id}/prescription/apply")
            ->assertOk()
            ->json('data');

        $this->assertSame(array_column($first, 'id'), array_column($again, 'id'));
        $this->assertSame(1, Medication::query()->where('user_id', $patient->id)->count());

        $this->actingAs($patient)
            ->getJson("api/v1/consultations/{$id}/prescription")
            ->assertJsonPath('data.added_to_schedule', true);

        $this->actingAs($doctor)
            ->postJson("api/v1/doctor/consultations/{$id}/prescription", ['diagnosis_summary' => 'Tonsillitis', 'drugs' => [self::DRUG]])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PRESCRIPTION_APPLIED');

        $this->actingAs($this->patient())
            ->postJson("api/v1/consultations/{$id}/prescription/apply")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'CONSULTATION_NOT_FOUND');

        $this->actingAs($doctor)
            ->postJson("api/v1/consultations/{$id}/prescription/apply")
            ->assertForbidden();
    }

    public function test_a_prescription_needs_the_session_to_have_started(): void
    {
        $patient = $this->patient();
        $doctor = $this->availableDoctor();
        $id = $this->book($patient, $doctor)['id'];

        $this->pay($id);

        $this->actingAs($doctor)
            ->postJson("api/v1/doctor/consultations/{$id}/prescription", ['diagnosis_summary' => 'Tonsillitis', 'drugs' => [self::DRUG]])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'NOT_ACTIVE');

        $this->actingAs($doctor)
            ->postJson("api/v1/doctor/consultations/{$id}/prescription", ['diagnosis_summary' => 'Tonsillitis', 'drugs' => []])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'drugs');
    }

    public function test_a_drug_in_an_allergy_class_is_refused_until_acknowledged(): void
    {
        $patient = $this->patient(['Penicillin']);
        $doctor = $this->availableDoctor();
        $id = $this->openSession($patient, $doctor);

        $this->actingAs($doctor)
            ->postJson("api/v1/doctor/consultations/{$id}/prescription", ['diagnosis_summary' => 'Tonsillitis', 'drugs' => [self::DRUG]])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ALLERGY_CONFLICT')
            ->assertJsonPath('error.field', 'drugs')
            ->assertJsonPath('error.meta.conflicts.0.drug_name', 'Amoxicillin')
            ->assertJsonPath('error.meta.conflicts.0.allergy_name', 'Penicillin')
            ->assertJsonPath('error.meta.conflicts.0.drug_class', fn($class) => is_string($class));

        $this->actingAs($doctor)
            ->postJson("api/v1/doctor/consultations/{$id}/prescription", ['diagnosis_summary' => 'Tonsillitis', 'drugs' => [['drug_name' => 'Azithromycin'] + self::DRUG]])
            ->assertCreated();

        $this->actingAs($doctor)
            ->postJson("api/v1/doctor/consultations/{$id}/prescription", ['diagnosis_summary' => 'Tonsillitis', 'drugs' => [self::DRUG], 'acknowledge_allergy_conflicts' => true])
            ->assertCreated();
    }

    public function test_the_patient_rates_a_finished_session_once(): void
    {
        $patient = $this->patient();
        $doctor = $this->availableDoctor();
        $id = $this->openSession($patient, $doctor);

        $this->actingAs($patient)
            ->postJson("api/v1/consultations/{$id}/rate", ['rating' => 5, 'review' => 'Clear and kind.'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'CONSULTATION_NOT_COMPLETE');

        $this->doctorDoes($doctor, $id, 'end');

        $this->actingAs($patient)
            ->postJson("api/v1/consultations/{$id}/rate", ['rating' => 4, 'review' => 'Clear and kind.'])
            ->assertOk();

        $this->actingAs($patient)
            ->postJson("api/v1/consultations/{$id}/rate", ['rating' => 5])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ALREADY_RATED');

        $this->actingAs($patient)
            ->getJson("api/v1/consultations/{$id}")
            ->assertJsonPath('data.rated', true);

        $profile = DoctorProfile::query()->where('user_id', $doctor->id)->first();

        $this->assertSame(1, $profile->rating_count);
        $this->assertEquals(4, $profile->rating_mean);
    }

    public function test_both_sides_join_a_call_room_through_the_mock_provider(): void
    {
        $patient = $this->patient();
        $doctor = $this->availableDoctor();
        $chat = $this->openSession($patient, $doctor);

        $this->actingAs($patient)
            ->postJson("api/v1/consultations/{$chat}/call")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'NOT_A_CALL');

        $this->doctorDoes($doctor, $chat, 'end');

        $id = $this->book($patient, $doctor, ['session_type' => 'video'])['id'];

        $this->pay($id);
        $this->doctorDoes($doctor, $id, 'accept');

        $this->actingAs($patient)
            ->postJson("api/v1/consultations/{$id}/call")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'CALL_NOT_OPEN');

        $this->doctorDoes($doctor, $id, 'start');

        $mine = $this->actingAs($patient)
            ->postJson("api/v1/consultations/{$id}/call")
            ->assertOk()
            ->assertJsonPath('data.provider', 'mock')
            ->assertJsonPath('data.can_share_screen', false)
            ->json('data');

        $theirs = $this->actingAs($doctor)
            ->postJson("api/v1/doctor/consultations/{$id}/call")
            ->assertOk()
            ->assertJsonPath('data.can_share_screen', true)
            ->json('data');

        $this->assertSame($mine['room'], $theirs['room']);
        $this->assertNotSame($mine['token'], $theirs['token']);
    }

    public function test_a_daily_room_is_made_and_deleted_at_the_end(): void
    {
        config(['elevenmed.calls.provider' => 'daily', 'elevenmed.calls.daily.api_key' => 'test-key']);

        Http::fake([
            'api.daily.co/v1/rooms' => Http::response(['name' => 'room-1', 'url' => 'https://11med.daily.co/room-1']),
            'api.daily.co/v1/meeting-tokens' => Http::response(['token' => 'tok-1']),
            'api.daily.co/v1/rooms/*' => Http::response(['deleted' => true]),
        ]);

        $patient = $this->patient();
        $doctor = $this->availableDoctor();
        $id = $this->openSession($patient, $doctor, ['session_type' => 'audio']);

        $this->actingAs($patient)
            ->postJson("api/v1/consultations/{$id}/call")
            ->assertOk()
            ->assertJsonPath('data.provider', 'daily')
            ->assertJsonPath('data.token', 'tok-1');

        $this->doctorDoes($doctor, $id, 'end');

        Http::assertSent(fn($request) => $request->method() === 'DELETE');
        Http::assertSent(fn($request) => $request->hasHeader('Authorization', 'Bearer test-key'));
    }

    public function test_an_unknown_call_provider_is_refused(): void
    {
        config(['elevenmed.calls.provider' => 'agora']);

        $patient = $this->patient();
        $id = $this->openSession($patient, $this->availableDoctor(), ['session_type' => 'audio']);

        $this->actingAs($patient)
            ->postJson("api/v1/consultations/{$id}/call")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'CALL_UNAVAILABLE');
    }
}
