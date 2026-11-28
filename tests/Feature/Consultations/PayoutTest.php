<?php

namespace Tests\Feature\Consultations;

use App\Models\Consultation;
use App\Models\ConsultationMessage;
use App\Models\Payout;
use App\Models\User;
use App\Services\Identity\AccountEraser;
use Tests\TestCase;

class PayoutTest extends TestCase
{
    use MakesConsultations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeSeams();
    }

    private function finished(User $doctor): string
    {
        $id = $this->openSession($this->patient(), $doctor);

        $this->doctorDoes($doctor, $id, 'end');

        return $id;
    }

    public function test_finished_sessions_are_paid_out_at_the_platforms_commission(): void
    {
        $doctor = $this->availableDoctor();

        $this->finished($doctor);
        $this->finished($doctor);
        $this->openSession($this->patient(), $doctor);

        $this->actingAs($doctor)
            ->getJson('api/v1/doctor/earnings')
            ->assertOk()
            ->assertJsonPath('data.pending_kobo', 850000)
            ->assertJsonPath('data.commission_percent', 15)
            ->assertJsonPath('data.payouts', []);

        $this->artisan('consultations:payouts')->assertSuccessful();
        $this->artisan('consultations:payouts')->assertSuccessful();

        $this->assertSame(1, Payout::query()->count());

        $this->actingAs($doctor)
            ->getJson('api/v1/doctor/earnings')
            ->assertJsonPath('data.pending_kobo', 0)
            ->assertJsonPath('data.payouts.0.gross_kobo', 1000000)
            ->assertJsonPath('data.payouts.0.commission_kobo', 150000)
            ->assertJsonPath('data.payouts.0.net_kobo', 850000)
            ->assertJsonPath('data.payouts.0.consultation_count', 2)
            ->assertJsonPath('data.payouts.0.status', 'pending');
    }

    public function test_the_desk_settles_a_payout_once(): void
    {
        $doctor = $this->availableDoctor();
        $admin = $this->admin();

        $this->finished($doctor);
        $this->artisan('consultations:payouts');

        $payout = $this->actingAs($admin)
            ->getJson('api/v1/portal/admin/payouts?status=pending')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.doctor_id', $doctor->uuid)
            ->assertJsonPath('data.items.0.bank_account_masked', fn(string $masked) => str_ends_with($masked, '6789'))
            ->json('data.items.0');

        $this->actingAs($admin)
            ->postJson("api/v1/portal/admin/payouts/{$payout['id']}/settle", ['decision' => 'paid'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'reference');

        $this->actingAs($admin)
            ->postJson("api/v1/portal/admin/payouts/{$payout['id']}/settle", ['decision' => 'paid', 'reference' => 'TRF-2026-0001'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.note', 'TRF-2026-0001');

        $this->actingAs($admin)
            ->postJson("api/v1/portal/admin/payouts/{$payout['id']}/settle", ['decision' => 'failed', 'reason' => 'Account closed'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'PAYOUT_SETTLED');

        $this->actingAs($admin)
            ->postJson('api/v1/portal/admin/payouts/0190d6f0-0000-7000-8000-000000000000/settle', ['decision' => 'paid', 'reference' => 'TRF-1'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'PAYOUT_NOT_FOUND');

        $this->actingAs($doctor)
            ->getJson('api/v1/doctor/earnings')
            ->assertJsonPath('data.paid_total_kobo', 425000);

        $this->actingAs($doctor)
            ->getJson('api/v1/portal/admin/payouts')
            ->assertForbidden();
    }

    public function test_a_failed_payout_returns_its_sessions_to_the_next_one(): void
    {
        $doctor = $this->availableDoctor();
        $admin = $this->admin();

        $this->finished($doctor);
        $this->artisan('consultations:payouts');

        $first = Payout::query()->sole();

        $this->actingAs($admin)
            ->postJson("api/v1/portal/admin/payouts/{$first->uuid}/settle", ['decision' => 'failed', 'reason' => 'Account closed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'failed');

        $this->artisan('consultations:payouts');

        $this->assertSame(2, Payout::query()->count());
        $this->assertSame(1, Payout::query()->where('status', 'pending')->sole()->consultation_count);
    }

    public function test_erasing_a_patient_keeps_what_the_doctor_is_owed_and_removes_the_rest(): void
    {
        $doctor = $this->availableDoctor();
        $patient = $this->patient();
        $done = $this->openSession($patient, $doctor);

        $this->actingAs($patient)->postJson("api/v1/consultations/{$done}/messages", ['body' => 'Thank you doctor'])->assertCreated();
        $this->doctorDoes($doctor, $done, 'end');

        $open = $this->book($patient, $this->availableDoctor())['id'];

        app(AccountEraser::class)->erase($patient->fresh(), 'Password1', null);

        $kept = Consultation::query()->where('uuid', $done)->sole();

        $this->assertNull($kept->patient_id);
        $this->assertSame('', $kept->complaint);
        $this->assertSame(0, ConsultationMessage::withTrashed()->where('consultation_id', $kept->id)->count());
        $this->assertNull(Consultation::withTrashed()->where('uuid', $open)->first());

        $this->artisan('consultations:payouts');

        $this->assertSame(1, Payout::query()->sole()->consultation_count);
    }
}
