<?php

namespace Tests\Feature\Medications;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComplianceTest extends TestCase
{
    use MedicationTesting;

    public function test_compliance_is_taken_over_scheduled_per_lagos_day(): void
    {
        $patient = $this->patient();

        // Created 07:30 on the 14th in Lagos, a dose at 08:00 and 20:00.
        $this->travelTo(Carbon::parse('2026-11-14T06:30:00Z'));
        $medication = $this->schedule($patient, ['start_date' => '2026-11-14']);

        $this->travelTo(Carbon::parse('2026-11-14T07:10:00Z'));
        $this->answer($patient, $medication['id'], '2026-11-14T07:00:00.000Z', 'taken');
        $this->travelTo(Carbon::parse('2026-11-15T07:10:00Z'));
        $this->answer($patient, $medication['id'], '2026-11-15T07:00:00.000Z', 'skipped');
        $this->travelTo(Carbon::parse('2026-11-15T19:30:00Z'));
        $this->answer($patient, $medication['id'], '2026-11-15T19:00:00.000Z', 'taken_late');

        // 00:30 on the 17th in Lagos, still the 16th in UTC.
        $this->travelTo(Carbon::parse('2026-11-16T23:30:00Z'));
        Artisan::call('medications:settle-doses');

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}/compliance?window=30&date=2026-11-16")
            ->assertOk()
            ->assertJsonPath('data.window_days', 7)
            ->assertJsonPath('data.doses_scheduled', 6)
            ->assertJsonPath('data.doses_taken', 2)
            ->assertJsonPath('data.score', 33)
            ->assertJsonPath('data.band', 'poor')
            ->assertJsonCount(7, 'data.daily')
            ->assertJsonPath('data.daily.0', ['date' => '2026-11-10', 'score' => null, 'taken' => 0, 'scheduled' => 0])
            ->assertJsonPath('data.daily.4', ['date' => '2026-11-14', 'score' => 50, 'taken' => 1, 'scheduled' => 2])
            ->assertJsonPath('data.daily.5', ['date' => '2026-11-15', 'score' => 50, 'taken' => 1, 'scheduled' => 2])
            ->assertJsonPath('data.daily.6', ['date' => '2026-11-16', 'score' => 0, 'taken' => 0, 'scheduled' => 2]);

        // Without a date the server's Lagos day is the 17th, whose doses are still to come.
        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}/compliance")
            ->assertJsonPath('data.daily.6', ['date' => '2026-11-17', 'score' => 0, 'taken' => 0, 'scheduled' => 2]);
    }

    public function test_an_empty_record_scores_zero_with_null_days(): void
    {
        $patient = $this->patient();

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}/compliance?window=abc")
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'window');

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}/compliance?window=0")
            ->assertOk()
            ->assertJsonPath('data.window_days', 1)
            ->assertJsonPath('data.score', 0)
            ->assertJsonPath('data.daily.0.score', null);
    }

    public function test_premium_reads_the_full_window(): void
    {
        $this->premium();

        $patient = $this->patient();

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}/compliance?window=30")
            ->assertJsonPath('data.window_days', 30)
            ->assertJsonCount(30, 'data.daily');
    }

    public function test_the_report_is_premium(): void
    {
        $patient = $this->patient();

        $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}/report")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PREMIUM_REQUIRED');
    }

    public function test_a_premium_patient_gets_a_pdf_behind_a_lapsing_link(): void
    {
        Storage::fake('local');
        $this->premium();
        $this->travelTo(Carbon::parse('2026-11-17T06:30:00Z'));

        $patient = $this->patient();
        $medication = $this->schedule($patient);
        $this->answer($patient, $medication['id'], '2026-11-17T07:00:00.000Z', 'skipped', 'side_effects');

        $report = $this->actingAs($patient)
            ->getJson("api/v1/medications/{$patient->uuid}/report?window=3")
            ->assertOk()
            ->assertJsonPath('data.window_days', 7)
            ->assertJsonPath('data.generated_at', '2026-11-17T06:30:00.000Z')
            ->assertJsonPath('data.expires_at', '2026-11-17T06:45:00.000Z')
            ->json('data');

        $file = $this->get($report['url'])->assertOk();

        $this->assertSame('application/pdf', $file->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $file->streamedContent());

        $this->travel(16)->minutes();

        $this->getJson($report['url'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'LINK_EXPIRED');

        $this->travel(1)->days();
        Artisan::call('medications:prune-reports');

        $this->assertSame([], Storage::disk('local')->files('compliance-reports'));
    }

    private function answer($patient, string $medicationId, string $instant, string $state, ?string $reason = null): void
    {
        $this->actingAs($patient)
            ->postJson('api/v1/medications/doses/log', [
                'dose_id' => $this->doseId($medicationId, $instant),
                'state' => $state,
                'skip_reason' => $reason,
                'logged_at' => now()->toIso8601ZuluString(),
            ])
            ->assertOk();
    }
}
