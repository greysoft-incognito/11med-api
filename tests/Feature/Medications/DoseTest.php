<?php

namespace Tests\Feature\Medications;

use App\Events\Medications\DoseMissed;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DoseTest extends TestCase
{
    use MedicationTesting;

    private const MORNING = '2026-11-17T07:00:00.000Z';

    private const EVENING = '2026-11-17T19:00:00.000Z';

    protected function setUp(): void
    {
        parent::setUp();

        // 07:30 in Lagos.
        $this->travelTo(Carbon::parse('2026-11-17T06:30:00Z'));
    }

    public function test_a_days_doses_are_due_at_lagos_times(): void
    {
        $patient = $this->patient();
        $medication = $this->schedule($patient);

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}/doses")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $this->doseId($medication['id'], self::MORNING))
            ->assertJsonPath('data.0.scheduled_for', self::MORNING)
            ->assertJsonPath('data.0.state', null)
            ->assertJsonPath('data.0.snooze_count', 0)
            ->assertJsonPath('data.0.medication_name', 'Amlodipine')
            ->assertJsonPath('data.1.scheduled_for', self::EVENING);

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}/doses?date=2026-11-18")
            ->assertJsonPath('data.0.scheduled_for', '2026-11-18T07:00:00.000Z');

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}/doses?date=17-11-2026")
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'date');
    }

    public function test_a_dose_is_logged_and_retracted(): void
    {
        $patient = $this->patient();
        $medication = $this->schedule($patient);
        $dose = $this->doseId($medication['id'], self::MORNING);

        $this->travelTo(Carbon::parse('2026-11-17T07:05:00Z'));

        $this->log($patient, $dose, 'skipped', ['skip_reason' => 'ran_out'])->assertOk()->assertJsonPath('data', null);
        $this->log($patient, $dose, 'taken')->assertOk();

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}/doses")
            ->assertJsonPath('data.0.state', 'taken')
            ->assertJsonPath('data.0.skip_reason', null)
            ->assertJsonPath('data.0.logged_at', '2026-11-17T07:05:00.000Z');

        $this->actingAs($patient)
            ->deleteJson('api/v1/medications/doses/log/' . rawurlencode($dose))
            ->assertOk();

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}/doses")
            ->assertJsonPath('data.0.state', null);

        $this->actingAs($patient)
            ->deleteJson('api/v1/medications/doses/log/' . rawurlencode($dose))
            ->assertNotFound()
            ->assertJsonPath('error.code', 'DOSE_NOT_ANSWERED');
    }

    public function test_a_dose_snoozes_twice_and_no_more(): void
    {
        $patient = $this->patient();
        $medication = $this->schedule($patient);
        $dose = $this->doseId($medication['id'], self::MORNING);

        $this->log($patient, $dose, 'snoozed')->assertOk();
        $this->log($patient, $dose, 'snoozed')->assertOk();

        $this->log($patient, $dose, 'snoozed')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SNOOZE_LIMIT_REACHED');

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}/doses")
            ->assertJsonPath('data.0.state', 'snoozed')
            ->assertJsonPath('data.0.snooze_count', 2);
    }

    public function test_answers_to_what_is_not_a_dose_are_refused(): void
    {
        $patient = $this->patient();
        $medication = $this->schedule($patient);

        foreach ([
            $this->doseId($medication['id'], '2026-11-17T07:30:00.000Z'),
            $this->doseId($medication['id'], '2026-11-09T07:00:00.000Z'),
            $this->doseId($medication['id'], '2026-11-16T07:00:00.000Z'),
            $this->doseId('0194e7a0-0000-7000-8000-000000000000', self::MORNING),
            'not-a-dose',
        ] as $dose) {
            $this->log($patient, $dose, 'taken')
                ->assertNotFound()
                ->assertJsonPath('error.code', 'DOSE_NOT_FOUND')
                ->assertJsonPath('error.field', 'dose_id');
        }

        $this->log($this->patient(), $this->doseId($medication['id'], self::MORNING), 'taken')
            ->assertNotFound();

        $this->actingAs($patient)
            ->postJson('api/v1/medications/doses/log', [
                'dose_id' => $this->doseId($medication['id'], self::MORNING),
                'state' => 'taken',
                'logged_at' => '2026-11-17T09:00:00Z',
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'logged_at');

        $this->actingAs($patient)
            ->postJson('api/v1/medications/doses/log', ['dose_id' => 'x', 'state' => 'eaten', 'logged_at' => now()->toIso8601ZuluString()])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'state');
    }

    public function test_the_offline_batch_clamps_snoozes_and_drops_deleted_schedules(): void
    {
        $patient = $this->patient();
        $medication = $this->schedule($patient);
        $gone = $this->schedule($patient, ['drug_name' => 'Gone']);
        $this->actingAs($patient)->deleteJson("api/v1/medications/{$gone['id']}");

        $dose = $this->doseId($medication['id'], self::MORNING);
        $at = fn(int $minutes) => Carbon::parse('2026-11-17T06:00:00Z')->addMinutes($minutes)->toIso8601ZuluString();

        $this->actingAs($patient)
            ->postJson('api/v1/medications/doses/log-batch', ['entries' => [
                ['dose_id' => $dose, 'state' => 'taken', 'logged_at' => $at(20)],
                ['dose_id' => $dose, 'state' => 'snoozed', 'logged_at' => $at(1)],
                ['dose_id' => $dose, 'state' => 'snoozed', 'logged_at' => $at(2)],
                ['dose_id' => $dose, 'state' => 'snoozed', 'logged_at' => $at(3)],
                ['dose_id' => $this->doseId($gone['id'], self::MORNING), 'state' => 'taken', 'logged_at' => $at(4)],
            ]])
            ->assertOk();

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}/doses")
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.state', 'taken')
            ->assertJsonPath('data.0.snooze_count', 2);

        $this->actingAs($patient)
            ->postJson('api/v1/medications/doses/log-batch', ['entries' => []])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'entries');
    }

    public function test_an_unanswered_or_snoozed_dose_is_missed_an_hour_after_due_once(): void
    {
        Event::fake([DoseMissed::class]);

        $patient = $this->patient();
        $medication = $this->schedule($patient);
        $other = $this->schedule($patient, ['drug_name' => 'Metformin', 'dosage_strength' => '500mg', 'specific_times' => ['08:00']]);

        $this->log($patient, $this->doseId($other['id'], self::MORNING), 'snoozed');

        $this->travelTo(Carbon::parse('2026-11-17T07:59:00Z'));
        Artisan::call('medications:settle-doses');
        Event::assertNotDispatched(DoseMissed::class);

        $this->travelTo(Carbon::parse('2026-11-17T08:00:00Z'));
        Artisan::call('medications:settle-doses');
        Artisan::call('medications:settle-doses');

        Event::assertDispatchedTimes(DoseMissed::class, 2);
        Event::assertDispatched(
            DoseMissed::class,
            fn(DoseMissed $e) => $e->medicationId === $medication['id']
                && $e->scheduledFor->toIso8601ZuluString('millisecond') === self::MORNING
                && $e->patient->is($patient)
                && $e->strength === '5mg'
        );

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}/doses")
            ->assertJsonPath('data.0.state', 'missed')
            ->assertJsonPath('data.0.logged_at', null)
            ->assertJsonPath('data.1.state', 'missed')
            ->assertJsonPath('data.1.snooze_count', 1);

        $dose = $this->doseId($medication['id'], self::MORNING);

        $this->actingAs($patient)
            ->deleteJson('api/v1/medications/doses/log/' . rawurlencode($dose))
            ->assertNotFound()
            ->assertJsonPath('error.code', 'DOSE_NOT_ANSWERED');

        $this->log($patient, $dose, 'taken_late')->assertOk();

        $this->actingAs($patient)
            ->deleteJson('api/v1/medications/doses/log/' . rawurlencode($dose))
            ->assertOk();

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}/doses")
            ->assertJsonPath('data.0.state', 'missed');
    }

    public function test_a_paused_schedule_owes_no_doses_for_the_pause(): void
    {
        $patient = $this->patient();
        $medication = $this->schedule($patient);

        $this->actingAs($patient)->postJson("api/v1/medications/{$medication['id']}/pause");

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}/doses")
            ->assertJsonCount(0, 'data');

        $this->travelTo(Carbon::parse('2026-11-19T12:00:00Z'));
        Artisan::call('medications:settle-doses');

        $this->actingAs($patient)->postJson("api/v1/medications/{$medication['id']}/resume")->assertOk();
        Artisan::call('medications:settle-doses');

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}/doses?date=2026-11-18")
            ->assertJsonCount(0, 'data');

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}/doses?date=2026-11-19")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.scheduled_for', '2026-11-19T19:00:00.000Z');
    }

    public function test_a_new_timing_keeps_the_doses_already_settled(): void
    {
        $patient = $this->patient();
        $medication = $this->schedule($patient);

        $this->log($patient, $this->doseId($medication['id'], self::MORNING), 'taken');

        $this->travelTo(Carbon::parse('2026-11-17T10:00:00Z'));
        Artisan::call('medications:settle-doses');

        $this->actingAs($patient)
            ->patchJson("api/v1/medications/{$medication['id']}", ['specific_times' => ['09:00', '21:00']])
            ->assertOk();

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}/doses")
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.scheduled_for', self::MORNING)
            ->assertJsonPath('data.0.state', 'taken')
            ->assertJsonPath('data.1.scheduled_for', '2026-11-17T20:00:00.000Z');
    }

    public function test_history_is_newest_first_paged_and_seven_days_free(): void
    {
        $patient = $this->patient();

        $this->travelTo(Carbon::parse('2026-11-01T06:30:00Z'));
        $medication = $this->schedule($patient, ['start_date' => '2026-11-01', 'specific_times' => ['08:00']]);
        $this->log($patient, $this->doseId($medication['id'], '2026-11-01T07:00:00.000Z'), 'taken');

        $this->travelTo(Carbon::parse('2026-11-17T09:00:00Z'));
        Artisan::call('medications:settle-doses');

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}/history?days=30&limit=3")
            ->assertOk()
            ->assertJsonPath('data.total', 7)
            ->assertJsonCount(3, 'data.items')
            ->assertJsonPath('data.items.0.scheduled_for', self::MORNING)
            ->assertJsonPath('data.items.0.state', 'missed')
            ->assertJsonPath('data.items.2.scheduled_for', '2026-11-15T07:00:00.000Z');

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}/history?medication_id={$medication['id']}")
            ->assertJsonPath('data.total', 7);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function log(User $patient, string $dose, string $state, array $extra = []): TestResponse
    {
        return $this->actingAs($patient)
            ->postJson('api/v1/medications/doses/log', [
                'dose_id' => $dose,
                'state' => $state,
                'logged_at' => now()->toIso8601ZuluString('millisecond'),
                ...$extra,
            ]);
    }
}
