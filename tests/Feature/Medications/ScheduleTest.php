<?php

namespace Tests\Feature\Medications;

use App\Models\Medication;
use App\Services\Medications\PrescribedSchedules;
use App\Services\Profile\CurrentMedications;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use MedicationTesting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-17T09:30:00Z'));
    }

    public function test_a_patient_adds_a_schedule_and_lists_it(): void
    {
        $patient = $this->patient();

        $created = $this->actingAs($patient)
            ->postJson('api/v1/medications', [
                'drug_name' => 'Paracetamol syrup',
                'brand_name' => null,
                'dosage_strength' => '120mg/5ml',
                'dosage_form' => 'syrup',
                'dose_quantity' => 2.5,
                'dose_unit' => 'ml',
                'frequency_type' => 'three_times_daily',
                'specific_times' => ['20:00', '08:00', '14:00'],
                'start_date' => '2026-11-17',
                'end_date' => '2026-11-21',
                'reminder_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.specific_times', ['08:00', '14:00', '20:00'])
            ->assertJsonPath('data.dose_quantity', 2.5)
            ->assertJsonPath('data.dose_unit', 'ml')
            ->assertJsonPath('data.with_food', 'not_specified')
            ->assertJsonPath('data.caregiver_alerts', false)
            ->assertJsonPath('data.total_duration_days', 5)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.paused_at', null)
            ->assertJsonPath('data.prescribed_by_doctor_id', null)
            ->json('data');

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}")
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.next_cursor', null)
            ->assertJsonPath('data.items.0.id', $created['id']);
    }

    public function test_the_request_is_validated_field_by_field(): void
    {
        $patient = $this->patient();

        $base = [
            'drug_name' => 'Amlodipine',
            'dosage_strength' => '5mg',
            'dosage_form' => 'tablet',
            'dose_quantity' => 1,
            'frequency_type' => 'daily',
            'specific_times' => ['08:00'],
            'start_date' => '2026-11-17',
            'reminder_active' => true,
        ];

        foreach ([
            'specific_times.0' => ['specific_times' => ['8am']],
            'end_date' => ['end_date' => '2026-11-16'],
            'dosage_form' => ['dosage_form' => 'patch'],
            'dose_quantity' => ['dose_quantity' => 0],
        ] as $field => $override) {
            $this->actingAs($patient)
                ->postJson('api/v1/medications', [...$base, ...$override])
                ->assertStatus(400)
                ->assertJsonPath('error.code', 'VALIDATION_FAILED')
                ->assertJsonPath('error.field', $field);
        }

        $medication = $this->schedule($patient);

        $this->actingAs($patient)
            ->patchJson("api/v1/medications/{$medication['id']}", ['end_date' => '2026-01-01'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'end_date');
    }

    public function test_the_free_tier_holds_three_active_schedules(): void
    {
        $patient = $this->patient();

        $first = $this->schedule($patient);
        $this->schedule($patient, ['drug_name' => 'Metformin']);
        $this->schedule($patient, ['drug_name' => 'Lisinopril']);

        $this->actingAs($patient)
            ->postJson('api/v1/medications', ['drug_name' => 'Atorvastatin'] + $this->body())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'MEDICATION_LIMIT_REACHED')
            ->assertJsonPath('error.meta', ['limit' => 3, 'active' => 3]);

        $this->actingAs($patient)
            ->postJson("api/v1/medications/{$first['id']}/pause")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->schedule($patient, ['drug_name' => 'Atorvastatin']);

        $this->actingAs($patient)
            ->postJson("api/v1/medications/{$first['id']}/resume")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'MEDICATION_LIMIT_REACHED');
    }

    public function test_an_ended_course_frees_its_place_and_reopening_it_is_gated(): void
    {
        $patient = $this->patient();

        $ended = $this->schedule($patient, ['start_date' => '2026-11-01', 'end_date' => '2026-11-10']);
        $this->schedule($patient);
        $this->schedule($patient);
        $this->schedule($patient);

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}")
            ->assertJsonPath('data.items.0.is_active', false);

        $this->actingAs($patient)
            ->patchJson("api/v1/medications/{$ended['id']}", ['end_date' => null])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'MEDICATION_LIMIT_REACHED');
    }

    public function test_prescribed_schedules_are_not_capped(): void
    {
        $patient = $this->patient();
        $doctor = $this->doctor();

        $this->schedule($patient);
        $this->schedule($patient);
        $this->schedule($patient);

        $applied = app(PrescribedSchedules::class)->apply($patient, $doctor, (string) Str::uuid(), 'Malaria', [[
            'drug_name' => 'Artemether/Lumefantrine',
            'dosage_strength' => '80/480mg',
            'dosage_form' => 'tablet',
            'dose_quantity' => 1,
            'dose_unit' => null,
            'times' => ['20:00', '08:00'],
            'duration_days' => 3,
            'special_instructions' => 'After food',
        ]]);

        $this->assertSame('twice_daily', $applied->first()->frequency_type->value);
        $this->assertSame('2026-11-19', $applied->first()->end_date->toDateString());

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}?limit=10")
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.items.3.prescribed_by_doctor_id', $doctor->uuid)
            ->assertJsonPath('data.items.3.condition_being_treated', 'Malaria');
    }

    public function test_premium_holds_any_number(): void
    {
        $this->premium();

        $patient = $this->patient();

        foreach (range(1, 4) as $n) {
            $this->schedule($patient, ['drug_name' => "Medicine {$n}"]);
        }
    }

    public function test_applying_a_prescription_twice_schedules_it_once(): void
    {
        $patient = $this->patient();
        $doctor = $this->doctor();
        $prescription = (string) Str::uuid();
        $drug = [
            'drug_name' => 'Amoxicillin',
            'dosage_strength' => '500mg',
            'dosage_form' => 'capsule',
            'dose_quantity' => 1,
            'dose_unit' => null,
            'times' => ['08:00', '14:00', '20:00'],
            'duration_days' => 5,
            'special_instructions' => null,
        ];

        $first = app(PrescribedSchedules::class)->apply($patient, $doctor, $prescription, 'Chest infection', [$drug]);
        $first->first()->delete();
        $again = app(PrescribedSchedules::class)->apply($patient, $doctor, $prescription, 'Chest infection', [$drug]);

        $this->assertSame($first->modelKeys(), $again->modelKeys());
        $this->assertSame(1, Medication::withTrashed()->where('prescription_id', $prescription)->count());
    }

    public function test_pausing_twice_keeps_when_it_began_and_delete_removes_it(): void
    {
        $patient = $this->patient();
        $medication = $this->schedule($patient);

        $pausedAt = $this->actingAs($patient)
            ->postJson("api/v1/medications/{$medication['id']}/pause")
            ->json('data.paused_at');

        $this->travel(5)->minutes();

        $this->actingAs($patient)
            ->postJson("api/v1/medications/{$medication['id']}/pause")
            ->assertJsonPath('data.paused_at', $pausedAt);

        $this->actingAs($patient)
            ->postJson("api/v1/medications/{$medication['id']}/resume")
            ->assertJsonPath('data.paused_at', null)
            ->assertJsonPath('data.is_active', true);

        $this->actingAs($patient)
            ->patchJson("api/v1/medications/{$medication['id']}", ['with_food' => 'after_food', 'brand_name' => 'Norvasc'])
            ->assertOk()
            ->assertJsonPath('data.with_food', 'after_food')
            ->assertJsonPath('data.brand_name', 'Norvasc')
            ->assertJsonPath('data.drug_name', 'Amlodipine');

        $this->actingAs($patient)
            ->deleteJson("api/v1/medications/{$medication['id']}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->actingAs($patient)
            ->deleteJson("api/v1/medications/{$medication['id']}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'MEDICATION_NOT_FOUND');
    }

    public function test_another_patients_schedule_does_not_exist_from_here(): void
    {
        $owner = $this->patient();
        $stranger = $this->patient();
        $medication = $this->schedule($owner);

        foreach (['patchJson' => '', 'postJson' => '/pause', 'deleteJson' => ''] as $method => $suffix) {
            $this->actingAs($stranger)
                ->{$method}("api/v1/medications/{$medication['id']}{$suffix}", [])
                ->assertNotFound()
                ->assertJsonPath('error.code', 'MEDICATION_NOT_FOUND');
        }

        foreach (['', '/doses', '/history', '/compliance', '/report'] as $suffix) {
            $this->actingAs($stranger)
                ->getJson("api/v1/medications/{$owner->uuid}{$suffix}")
                ->assertNotFound();
        }
    }

    public function test_an_accepted_caregiver_reads_the_schedule_but_not_the_report(): void
    {
        $this->premium();

        $patient = $this->patient();
        $carer = $this->patient();
        $this->schedule($patient);
        $this->careFor($patient, $carer);

        foreach (['', '/doses', '/history', '/compliance'] as $suffix) {
            $this->actingAs($carer)
                ->getJson("api/v1/medications/{$patient->uuid}{$suffix}")
                ->assertOk();
        }

        $this->actingAs($carer)
            ->getJson("api/v1/medications/{$patient->uuid}/report")
            ->assertNotFound();
    }

    public function test_a_doctor_account_is_refused(): void
    {
        $doctor = $this->doctor();

        $this->actingAs($doctor)
            ->getJson("api/v1/medications/{$doctor->uuid}")
            ->assertForbidden();

        $this->actingAs($doctor)
            ->postJson('api/v1/medications', $this->body())
            ->assertForbidden();
    }

    public function test_the_emergency_page_lists_active_schedules(): void
    {
        $patient = $this->patient();
        $this->schedule($patient);
        $paused = $this->schedule($patient, ['drug_name' => 'Paused']);
        $this->actingAs($patient)->postJson("api/v1/medications/{$paused['id']}/pause");

        $this->assertSame(
            [['name' => 'Amlodipine', 'strength' => '5mg', 'times' => ['08:00', '20:00']]],
            app(CurrentMedications::class)->forEmergency($patient)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function body(): array
    {
        return [
            'drug_name' => 'Amlodipine',
            'dosage_strength' => '5mg',
            'dosage_form' => 'tablet',
            'dose_quantity' => 1,
            'frequency_type' => 'daily',
            'specific_times' => ['08:00'],
            'start_date' => '2026-11-17',
            'reminder_active' => true,
        ];
    }
}
