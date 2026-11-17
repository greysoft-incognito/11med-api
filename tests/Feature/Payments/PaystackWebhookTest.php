<?php

namespace Tests\Feature\Payments;

use App\Enums\Role;
use App\Events\Payments\PaymentSucceeded;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Support\Payments\FakesPaystack;
use Tests\TestCase;

class PaystackWebhookTest extends TestCase
{
    use FakesPaystack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakePaystack();
    }

    public function test_charge_success_settles_the_payment_once(): void
    {
        Event::fake([PaymentSucceeded::class]);

        $patient = $this->patient();
        $order = $this->order($patient, 350000);
        $reference = $this->start($patient, $order->id);

        $this->charged($reference, 350000, 'bank')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->charged($reference, 350000)->assertOk();

        $this->actingAs($patient)
            ->getJson("api/v1/payments/{$reference}")
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.channel', 'bank_transfer')
            ->assertJsonPath('data.authorization_url', null)
            ->assertJsonPath('data.paid_at', '2026-11-17T10:00:00.000Z');

        Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
        Event::assertDispatched(PaymentSucceeded::class, fn(PaymentSucceeded $e) => $e->payment->targetIds() === [$order->id]);
    }

    public function test_a_bad_signature_is_refused_and_unknown_references_are_acknowledged(): void
    {
        $patient = $this->patient();
        $reference = $this->start($patient, $this->order($patient, 350000)->id);

        $this->webhook('charge.success', ['reference' => $reference, 'status' => 'success', 'amount' => 350000], 'sk_wrong')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'INVALID_SIGNATURE');

        $this->charged('11M-NOT-OURS', 350000)->assertOk();
        $this->webhook('subscription.create', [])->assertOk();

        $this->assertSame('pending', $this->statusOf($reference));
    }

    public function test_a_charge_short_of_the_amount_does_not_settle(): void
    {
        $patient = $this->patient();
        $reference = $this->start($patient, $this->order($patient, 350000)->id);

        $this->charged($reference, 100)->assertOk();

        $this->assertSame('pending', $this->statusOf($reference));
    }

    public function test_an_abandoned_payment_that_is_paid_anyway_counts_and_a_second_charge_is_returned(): void
    {
        $patient = $this->patient();
        $order = $this->order($patient, 350000);

        $old = $this->start($patient, $order->id);
        $new = $this->start($patient, $order->id);

        $this->charged($old, 350000)->assertOk();

        $this->assertSame('paid', $this->statusOf($old));
        $this->assertSame('abandoned', $this->statusOf($new));

        $this->charged($new, 350000)->assertOk();

        Http::assertSent(fn(Request $request) => $request->url() === 'https://api.paystack.co/refund'
            && $request['transaction'] === $new
            && $request['amount'] === 350000);

        $this->webhook('refund.processed', ['transaction_reference' => $new, 'amount' => 350000, 'status' => 'processed'])->assertOk();

        $this->assertSame('refunded', $this->statusOf($new));
        $this->assertSame('paid', $this->statusOf($old));
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
            ->app(Role::Patient)
            ->create();
    }
}
