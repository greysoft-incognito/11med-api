<?php

namespace Tests\Feature\Readings;

use App\Models\DoseLog;
use App\Models\Medication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Medications\MedicationTesting;
use Tests\TestCase;

class ReadingTest extends TestCase
{
    use MedicationTesting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-16T12:00:00Z'));
    }

    public function test_each_kind_of_reading_is_recorded_in_its_own_shape(): void
    {
        $patient = $this->patient();

        $this->actingAs($patient)
            ->postJson('api/v1/readings', ['type' => 'blood_pressure', 'systolic' => 128, 'diastolic' => 84, 'pulse' => null, 'kg' => 70])
            ->assertCreated()
            ->assertJsonPath('data.type', 'blood_pressure')
            ->assertJsonPath('data.systolic', 128)
            ->assertJsonPath('data.pulse', null)
            ->assertJsonPath('data.taken_at', '2026-11-16T12:00:00.000Z')
            ->assertJsonPath('data.note', null)
            ->assertJsonMissingPath('data.kg');

        $this->actingAs($patient)
            ->postJson('api/v1/readings', ['type' => 'blood_glucose', 'mmol_l' => 6.4, 'context' => 'fasting', 'taken_at' => '2026-11-16T06:00:00Z', 'note' => 'Before breakfast'])
            ->assertCreated()
            ->assertJsonPath('data.mmol_l', 6.4)
            ->assertJsonPath('data.context', 'fasting')
            ->assertJsonPath('data.taken_at', '2026-11-16T06:00:00.000Z');

        $this->actingAs($patient)
            ->postJson('api/v1/readings', ['type' => 'weight', 'kg' => 72.5])
            ->assertCreated()
            ->assertJsonPath('data.kg', 72.5);
    }

    public function test_impossible_values_and_future_times_are_refused_by_field(): void
    {
        $patient = $this->patient();

        foreach ([
            'systolic' => ['type' => 'blood_pressure', 'systolic' => 300, 'diastolic' => 80, 'pulse' => null],
            'pulse' => ['type' => 'blood_pressure', 'systolic' => 120, 'diastolic' => 80],
            'context' => ['type' => 'blood_glucose', 'mmol_l' => 5],
            'kg' => ['type' => 'weight', 'kg' => 5],
            'type' => ['type' => 'cholesterol'],
            'taken_at' => ['type' => 'weight', 'kg' => 70, 'taken_at' => '2026-11-16T12:10:00Z'],
        ] as $field => $body) {
            $this->actingAs($patient)
                ->postJson('api/v1/readings', $body)
                ->assertStatus(400)
                ->assertJsonPath('error.code', 'VALIDATION_FAILED')
                ->assertJsonPath('error.field', $field);
        }
    }

    public function test_the_list_is_newest_first_filtered_and_seven_days_free(): void
    {
        $patient = $this->patient();

        foreach (['2026-11-06T08:00:00Z', '2026-11-12T08:00:00Z', '2026-11-15T08:00:00Z'] as $at) {
            $this->actingAs($patient)->postJson('api/v1/readings', ['type' => 'weight', 'kg' => 70, 'taken_at' => $at]);
        }

        $this->actingAs($patient)->postJson('api/v1/readings', ['type' => 'blood_glucose', 'mmol_l' => 5.2, 'context' => 'random']);

        $this->actingAs($patient)
            ->getJson('api/v1/readings?type=weight&window=30&limit=1')
            ->assertOk()
            ->assertJsonPath('data.window_days', 7)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.0.taken_at', '2026-11-15T08:00:00.000Z')
            ->assertJsonPath('data.next_cursor', fn($cursor) => is_string($cursor));

        $this->actingAs($patient)
            ->getJson('api/v1/readings')
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.items.0.type', 'blood_glucose');

        $this->actingAs($patient)
            ->getJson('api/v1/readings?type=height')
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'type');
    }

    public function test_premium_lists_the_window_it_asks_for(): void
    {
        $this->premium();

        $patient = $this->patient();
        $this->actingAs($patient)->postJson('api/v1/readings', ['type' => 'weight', 'kg' => 70, 'taken_at' => '2026-11-06T08:00:00Z']);

        $this->actingAs($patient)
            ->getJson('api/v1/readings?window=30')
            ->assertJsonPath('data.window_days', 30)
            ->assertJsonPath('data.total', 1);
    }

    public function test_only_the_owner_deletes_a_reading(): void
    {
        $patient = $this->patient();
        $id = $this->actingAs($patient)
            ->postJson('api/v1/readings', ['type' => 'weight', 'kg' => 70])
            ->json('data.id');

        $this->actingAs($this->patient())
            ->deleteJson("api/v1/readings/{$id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'READING_NOT_FOUND');

        $this->actingAs($patient)
            ->deleteJson("api/v1/readings/{$id}")
            ->assertOk();

        $this->actingAs($patient)
            ->getJson('api/v1/readings')
            ->assertJsonPath('data.total', 0);
    }

    public function test_a_doctor_account_is_refused(): void
    {
        $doctor = $this->doctor();

        $this->actingAs($doctor)->getJson('api/v1/readings')->assertForbidden();
        $this->actingAs($doctor)->postJson('api/v1/readings', ['type' => 'weight', 'kg' => 70])->assertForbidden();
        $this->actingAs($doctor)->getJson('api/v1/insights')->assertForbidden();
    }

    public function test_insights_are_premium(): void
    {
        $this->actingAs($this->patient())
            ->getJson('api/v1/insights')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PREMIUM_REQUIRED');
    }

    public function test_insights_state_their_inputs_when_there_is_enough_behind_them(): void
    {
        $this->premium();

        $patient = $this->patient();

        $this->actingAs($patient)
            ->getJson('api/v1/insights')
            ->assertOk()
            ->assertExactJson(['success' => true, 'data' => ['window_days' => 30, 'items' => []], 'message' => 'OK', 'timestamp' => now()->toIso8601ZuluString('millisecond')]);

        $this->travelTo(Carbon::parse('2026-11-01T06:30:00Z'));
        $evening = $this->schedule($patient, ['drug_name' => 'Amlodipine', 'start_date' => '2026-11-01', 'specific_times' => ['08:00', '21:00']]);
        $morning = $this->schedule($patient, ['drug_name' => 'Metformin', 'start_date' => '2026-11-01', 'specific_times' => ['08:00']]);

        foreach ([$evening, $morning] as $schedule) {
            $medication = Medication::query()->where('uuid', $schedule['id'])->first();

            foreach (range(1, 15) as $day) {
                DoseLog::create([
                    'user_id' => $patient->id,
                    'medication_id' => $medication->id,
                    'scheduled_for' => Carbon::parse(sprintf('2026-11-%02dT07:00:00Z', $day)),
                    'state' => 'taken',
                    'logged_at' => Carbon::parse(sprintf('2026-11-%02dT07:05:00Z', $day)),
                ]);
            }
        }

        foreach ([['2026-10-20', 140, 90], ['2026-10-25', 138, 88], ['2026-11-01', 142, 92], ['2026-11-10', 150, 95], ['2026-11-12', 152, 96], ['2026-11-14', 148, 94]] as [$date, $systolic, $diastolic]) {
            $this->travelTo(Carbon::parse("{$date}T08:00:00Z"));
            $this->actingAs($patient)->postJson('api/v1/readings', ['type' => 'blood_pressure', 'systolic' => $systolic, 'diastolic' => $diastolic, 'pulse' => 70]);
        }

        $this->travelTo(Carbon::parse('2026-11-16T12:00:00Z'));
        Artisan::call('medications:settle-doses');

        $this->actingAs($patient)
            ->getJson('api/v1/insights')
            ->assertOk()
            ->assertJsonPath('data.items', [
                ['kind' => 'missed_by_time_of_day', 'slot' => 'night', 'missed_percent' => 100, 'other_percent' => 6, 'window_days' => 30],
                ['kind' => 'weakest_medication', 'medication_name' => 'Amlodipine', 'score' => 48, 'overall_score' => 64, 'window_days' => 30],
                ['kind' => 'reading_trend', 'reading_type' => 'blood_pressure', 'direction' => 'up', 'recent_value' => 150, 'earlier_value' => 140, 'recent_secondary' => 95, 'earlier_secondary' => 90, 'window_days' => 30],
            ]);
    }
}
