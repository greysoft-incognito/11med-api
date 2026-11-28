<?php

namespace Tests\Feature\Accounts;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\DoctorProfile;
use Tests\Concerns\MakesAdmins;
use Tests\Feature\Consultations\MakesConsultations;
use Tests\TestCase;

class SuspendedDoctorTest extends TestCase
{
    use MakesAdmins;
    use MakesConsultations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-01T09:00:00Z');
        $this->fakeSeams();
    }

    public function test_a_suspended_doctor_leaves_the_directory_and_their_bookings_are_withdrawn(): void
    {
        $doctor = $this->availableDoctor();
        $patient = $this->patient();
        $id = $this->book($patient, $doctor)['id'];

        $this->actingAs($patient)
            ->getJson('api/v1/doctors')
            ->assertJsonPath('data.total', 1);

        $this->actingAs($this->admin())
            ->postJson("api/v1/portal/admin/users/{$doctor->uuid}/suspend", ['reason' => 'Licence under MDCN review.'])
            ->assertOk();

        $this->assertSame(ConsultationStatus::Cancelled, Consultation::firstWhere('uuid', $id)->status);
        $this->assertFalse(DoctorProfile::firstWhere('user_id', $doctor->id)->is_available_now);

        $this->actingAs($patient)
            ->getJson('api/v1/doctors')
            ->assertJsonPath('data.total', 0);

        $this->actingAs($patient)
            ->getJson("api/v1/doctors/{$doctor->uuid}")
            ->assertNotFound();
    }
}
