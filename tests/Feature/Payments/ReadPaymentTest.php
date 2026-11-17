<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentChannel;
use App\Enums\PaymentPurpose;
use App\Enums\Role;
use App\Events\Payments\PaymentSucceeded;
use App\Models\User;
use App\Services\Payments\Payments;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Support\Payments\FakesPaystack;
use Tests\TestCase;

class ReadPaymentTest extends TestCase
{
    use FakesPaystack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakePaystack();
    }

    public function test_a_patient_reads_their_own_payment_and_nobody_elses(): void
    {
        $patient = $this->patient();
        $order = $this->order($patient, 350000);
        $reference = $this->start($patient, $order->id);

        $this->actingAs($patient)
            ->getJson("api/v1/payments/{$reference}")
            ->assertOk()
            ->assertJsonPath('data.reference', $reference)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.target_ids', [$order->id]);

        $this->actingAs($this->patient())
            ->getJson("api/v1/payments/{$reference}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'PAYMENT_NOT_FOUND');

        $doctor = User::factory()
            ->app(Role::Doctor)
            ->create();

        $this->actingAs($doctor)
            ->getJson("api/v1/payments/{$reference}")
            ->assertForbidden();
    }

    public function test_a_pending_payment_is_checked_with_the_gateway_at_most_every_few_seconds(): void
    {
        $patient = $this->patient();
        $order = $this->order($patient, 350000);
        $reference = $this->start($patient, $order->id);

        $this->verdicts[$reference] = ['status' => 'failed', 'amount' => 350000, 'gateway_response' => 'Declined'];

        $this->actingAs($patient)
            ->getJson("api/v1/payments/{$reference}")
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.failure_reason', 'Declined')
            ->assertJsonPath('data.authorization_url', null);

        $other = $this->start($patient, $order->id);

        $this->actingAs($patient)->getJson("api/v1/payments/{$other}")->assertOk();
        $this->verdicts[$other] = ['status' => 'success', 'amount' => 350000, 'channel' => 'ussd', 'paid_at' => '2026-11-17T10:00:00.000Z'];

        $this->actingAs($patient)
            ->getJson("api/v1/payments/{$other}")
            ->assertJsonPath('data.status', 'pending');

        $this->travel(16)->seconds();

        $this->actingAs($patient)
            ->getJson("api/v1/payments/{$other}")
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.channel', 'ussd')
            ->assertJsonPath('data.paid_at', '2026-11-17T10:00:00.000Z');

        Http::assertSentCount(5);
        Http::assertSent(fn(Request $request) => $request->url() === "https://api.paystack.co/transaction/verify/{$other}");
    }

    public function test_targets_read_their_summary_in_one_call(): void
    {
        $patient = $this->patient();
        $first = $this->order($patient, 350000, 'ORD-1');
        $second = $this->order($patient, 120000, 'ORD-2');
        $first->siblings = [$second];

        $abandoned = $this->start($patient, $first->id);
        $reference = $this->start($patient, $first->id);

        $summaries = app(Payments::class)->summariesFor(PaymentPurpose::Order, [$first->id, $second->id, $patient->uuid]);

        $this->assertSame([$first->id, $second->id], array_keys($summaries));
        $this->assertNotSame($abandoned, $reference);
        $this->assertSame([
            'reference' => $reference,
            'status' => 'pending',
            'channel' => null,
            'amount_kobo' => 470000,
            'authorization_url' => 'https://checkout.paystack.com/' . $reference,
            'paid_at' => null,
        ], $summaries[$second->id]);

        $this->assertNull(app(Payments::class)->summaryFor($this->order($patient, 100)));
    }

    public function test_the_pharmacy_marks_a_counter_payment_paid_on_collection(): void
    {
        Event::fake([PaymentSucceeded::class]);

        $patient = $this->patient();
        $order = $this->order($patient, 350000);

        $this->actingAs($patient)
            ->postJson('api/v1/payments', ['purpose' => 'order', 'target_id' => $order->id, 'channel' => 'counter'])
            ->assertCreated();

        app(Payments::class)->collectAtCounter($order);

        $this->assertSame('paid', app(Payments::class)->summaryFor($order)['status']);
        $this->assertSame('counter', app(Payments::class)->summaryFor($order)['channel']);

        Event::assertDispatched(PaymentSucceeded::class, fn(PaymentSucceeded $e) => $e->target === $order && $e->channel === PaymentChannel::Counter);
    }

    public function test_a_module_opens_a_counter_payment_and_starts_a_checkout_itself(): void
    {
        $patient = $this->patient();
        $pickup = $this->order($patient, 350000, 'ORD-1');
        $first = $this->order($patient, 200000, 'ORD-2');
        $second = $this->order($patient, 100000, 'ORD-3');

        app(Payments::class)->openAtCounter($pickup);
        app(Payments::class)->initialise([$first, $second], $patient, PaymentChannel::Card);

        $this->assertSame('counter', app(Payments::class)->summaryFor($pickup)['channel']);
        $this->assertNull(app(Payments::class)->summaryFor($pickup)['authorization_url']);
        $this->assertSame(300000, app(Payments::class)->summaryFor($second)['amount_kobo']);
        $this->assertSame(
            app(Payments::class)->summaryFor($first)['reference'],
            app(Payments::class)->summaryFor($second)['reference'],
        );
    }

    private function start(User $patient, string $targetId): string
    {
        return $this->actingAs($patient)
            ->postJson('api/v1/payments', ['purpose' => 'order', 'target_id' => $targetId])
            ->assertCreated()
            ->json('data.reference');
    }

    private function patient(): User
    {
        return User::factory()
            ->app(Role::Patient)
            ->create();
    }
}
