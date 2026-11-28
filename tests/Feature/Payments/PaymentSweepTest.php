<?php

namespace Tests\Feature\Payments;

use App\Enums\AccountKind;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Services\Payments\Refunds;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\Support\Payments\FakesPaystack;
use Tests\TestCase;

class PaymentSweepTest extends TestCase
{
    use FakesPaystack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakePaystack();
    }

    public function test_payments_pending_past_the_timeout_are_settled_or_abandoned(): void
    {
        $patient = $this->patient();
        $quiet = $this->start($patient, $this->order($patient, 100000)->id);
        $paid = $this->start($patient, $this->order($patient, 200000)->id);
        $this->actingAs($patient)
            ->postJson('api/v1/payments', ['purpose' => 'order', 'target_id' => $this->order($patient, 300000)->id, 'channel' => 'counter'])
            ->assertCreated();

        $this->verdicts[$paid] = ['status' => 'success', 'amount' => 200000, 'channel' => 'card'];

        $this->travel(61)->minutes();

        $recent = $this->start($patient, $this->order($patient, 400000)->id);

        $this->artisan('payments:sweep')
            ->expectsOutput('Abandoned 1, settled 1, refunds retried 0.')
            ->assertSuccessful();

        $this->assertSame('abandoned', $this->statusOf($quiet));
        $this->assertSame('paid', $this->statusOf($paid));
        $this->assertSame('pending', $this->statusOf($recent));
        $this->assertSame(1, Payment::query()->where('channel', 'counter')->where('status', 'pending')->count());
    }

    public function test_refunds_the_gateway_never_received_are_asked_again(): void
    {
        $patient = $this->patient();
        $order = $this->order($patient, 100000);
        $reference = $this->start($patient, $order->id);
        $this->charged($reference, 100000)->assertOk();

        Http::swap(new Factory);
        Http::fake(['api.paystack.co/*' => Http::response(['status' => false], 500)]);

        try {
            app(Refunds::class)->refund($order);
        } catch (\Throwable) {
        }

        $this->assertNull(PaymentRefund::query()->value('requested_at'));

        Http::swap(new Factory);
        $this->fakePaystack();
        $this->travel(16)->minutes();

        $this->artisan('payments:sweep')->assertSuccessful();

        $refund = PaymentRefund::query()->firstOrFail();

        $this->assertSame(RefundStatus::Pending, $refund->status);
        $this->assertNotNull($refund->requested_at);
        $this->assertSame('109000', $refund->gateway_refund_id);
    }

    private function start(User $patient, string $targetId): string
    {
        return $this->actingAs($patient)
            ->postJson('api/v1/payments', ['purpose' => 'order', 'target_id' => $targetId])
            ->assertCreated()
            ->json('data.reference');
    }

    private function statusOf(string $reference): string
    {
        return Payment::query()
            ->where('reference', $reference)
            ->firstOrFail()
            ->status
            ->value;
    }

    private function patient(): User
    {
        return User::factory()
            ->app(AccountKind::Patient)
            ->create();
    }
}
