<?php

namespace Tests\Feature\Payments;

use App\Enums\AccountKind;
use App\Enums\PaymentPurpose;
use App\Models\User;
use App\Services\Identity\AccountEraser;
use App\Services\Payments\Refunds;
use Tests\Support\Payments\FakesPaystack;
use Tests\Support\Payments\StubPayable;
use Tests\TestCase;

class AdminPaymentDeskTest extends TestCase
{
    use FakesPaystack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakePaystack();
    }

    public function test_the_desk_lists_each_order_share_newest_first_without_consultations(): void
    {
        $patient = User::factory()
            ->app(AccountKind::Patient)
            ->create(['full_name' => 'Ada Obi', 'phone' => '08031234567']);

        $first = $this->order($patient, 350000, 'ORD-1');
        $second = $this->order($patient, 120000, 'ORD-2');
        $first->siblings = [$second];
        $reference = $this->start($patient, $first);

        $consultation = new StubPayable(PaymentPurpose::Consultation, $patient, 500000, 'consult', 'Dr Bello');
        $this->payables->add($consultation);
        $this->start($patient, $consultation);

        $this->charged($reference, 470000)->assertOk();

        $this->actingAs($this->admin())
            ->getJson('api/v1/portal/admin/payments')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.next_cursor', null)
            ->assertJsonPath('data.items.0.target_id', $second->id)
            ->assertJsonPath('data.items.1', [
                'reference' => $reference,
                'purpose' => 'order',
                'target_id' => $first->id,
                'target_reference' => 'ORD-1',
                'partner_name' => 'Medplus Lekki',
                'patient_name' => 'Ada Obi',
                'patient_phone' => '08031234567',
                'amount_kobo' => 350000,
                'channel' => 'card',
                'status' => 'paid',
                'paid_at' => '2026-11-17T10:00:00.000Z',
                'created_at' => $this->json('GET', 'api/v1/portal/admin/payments')->json('data.items.1.created_at'),
                'failure_reason' => null,
            ]);
    }

    public function test_the_desk_filters_by_each_share_status_and_searches(): void
    {
        $patient = User::factory()
            ->app(AccountKind::Patient)
            ->create(['full_name' => 'Ada Obi', 'phone' => '08031234567']);

        $first = $this->order($patient, 350000, 'ORD-1');
        $second = $this->order($patient, 120000, 'ORD-2');
        $first->siblings = [$second];
        $reference = $this->start($patient, $first);
        $this->charged($reference, 470000)->assertOk();

        app(Refunds::class)->refund($first);
        $this->webhook('refund.processed', ['transaction_reference' => $reference, 'amount' => 350000])->assertOk();

        $admin = $this->admin();

        $this->actingAs($admin)
            ->getJson('api/v1/portal/admin/payments?status=refunded')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.target_reference', 'ORD-1');

        $this->actingAs($admin)
            ->getJson('api/v1/portal/admin/payments?status=paid')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.target_reference', 'ORD-2');

        foreach (['ada', '0803123', 'ord-2', 'medplus', strtolower($reference)] as $q) {
            $this->actingAs($admin)
                ->getJson('api/v1/portal/admin/payments?q=' . urlencode($q))
                ->assertJsonPath('data.total', $q === 'ord-2' ? 1 : 2);
        }

        $this->actingAs($admin)
            ->getJson('api/v1/portal/admin/payments?q=nobody')
            ->assertJsonPath('data.total', 0);

        $page = $this->actingAs($admin)
            ->getJson('api/v1/portal/admin/payments?limit=1')
            ->assertJsonPath('data.total', 2)
            ->json('data');

        $this->actingAs($admin)
            ->getJson('api/v1/portal/admin/payments?limit=1&cursor=' . $page['next_cursor'])
            ->assertJsonPath('data.items.0.target_reference', 'ORD-1')
            ->assertJsonPath('data.next_cursor', null);

        $this->actingAs($admin)
            ->getJson('api/v1/portal/admin/payments?status=lost')
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'status');
    }

    public function test_erasing_the_patient_keeps_the_payment_severed_and_off_the_desk(): void
    {
        $patient = $this->patient();
        $reference = $this->start($patient, $this->order($patient, 350000));
        $this->charged($reference, 350000)->assertOk();

        app(AccountEraser::class)->erase($patient, 'Password1', null);

        $this->assertDatabaseHas('payments', ['reference' => $reference, 'user_id' => null, 'status' => 'paid']);

        $this->actingAs($this->admin())
            ->getJson('api/v1/portal/admin/payments')
            ->assertJsonPath('data.total', 0);
    }

    public function test_only_the_team_reads_the_desk(): void
    {
        $patient = User::factory()
            ->app(AccountKind::Patient)
            ->create();

        $this->actingAs($patient)
            ->getJson('api/v1/portal/admin/payments')
            ->assertForbidden();

        $pharmacy = User::factory()
            ->portal(AccountKind::Pharmacy)
            ->create();

        $this->actingAs($pharmacy)
            ->getJson('api/v1/portal/admin/payments')
            ->assertForbidden();
    }

    private function start(User $patient, StubPayable $target): string
    {
        return $this->actingAs($patient)
            ->postJson('api/v1/payments', ['purpose' => $target->purpose->value, 'target_id' => $target->id])
            ->assertCreated()
            ->json('data.reference');
    }

    private function patient(): User
    {
        return User::factory()
            ->app(AccountKind::Patient)
            ->create();
    }
}
