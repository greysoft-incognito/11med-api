<?php

namespace Tests\Feature\Payments;

use App\Enums\AccountKind;
use App\Enums\PaymentPurpose;
use App\Events\Payments\PaymentAbandoned;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\Gateway\GatewayVerdict;
use App\Services\Payments\Settlement;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Support\Payments\FakesPaystack;
use Tests\Support\Payments\StubPayable;
use Tests\TestCase;

class InitialisePaymentTest extends TestCase
{
    use FakesPaystack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakePaystack();
    }

    public function test_one_payment_covers_every_unpaid_order_of_the_checkout(): void
    {
        $patient = $this->patient();
        $first = $this->order($patient, 350000, 'ORD-1');
        $second = $this->order($patient, 120000, 'ORD-2');
        $first->siblings = [$second];

        $payment = $this->actingAs($patient)
            ->postJson('api/v1/payments', ['purpose' => 'order', 'target_id' => $first->id, 'channel' => 'card'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.purpose', 'order')
            ->assertJsonPath('data.channel', 'card')
            ->assertJsonPath('data.amount_kobo', 470000)
            ->assertJsonPath('data.target_ids', [$first->id, $second->id])
            ->assertJsonPath('data.paid_at', null)
            ->assertJsonPath('data.failure_reason', null)
            ->assertJsonStructure(['data' => ['reference', 'authorization_url', 'created_at']])
            ->json('data');

        $this->assertStringStartsWith('11M-', $payment['reference']);
        $this->assertSame('https://checkout.paystack.com/' . $payment['reference'], $payment['authorization_url']);

        Http::assertSent(fn(Request $request) => $request->url() === 'https://api.paystack.co/transaction/initialize'
            && $request['amount'] === 470000
            && $request['currency'] === 'NGN'
            && $request['channels'] === ['card']
            && $request['email'] === $patient->uuid . '@customers.11med.ng'
            && $request['callback_url'] === 'elevenmed://pay?reference=' . $payment['reference']
            && $request['metadata']['target_ids'] === [$first->id, $second->id]
            && $request->hasHeader('Authorization', 'Bearer ' . self::SECRET));
    }

    public function test_restarting_abandons_the_pending_payment(): void
    {
        Event::fake([PaymentAbandoned::class]);

        $patient = $this->patient();
        $order = $this->order($patient, 350000);

        $first = $this->start($patient, $order)->json('data.reference');
        $second = $this->start($patient, $order)->json('data.reference');

        $this->assertNotSame($first, $second);

        $this->actingAs($patient)
            ->getJson("api/v1/payments/{$first}")
            ->assertOk()
            ->assertJsonPath('data.status', 'abandoned')
            ->assertJsonPath('data.authorization_url', null);

        Event::assertDispatched(PaymentAbandoned::class, fn(PaymentAbandoned $e) => $e->reference === $first && $e->target->paymentTargetId() === $order->id);
    }

    public function test_a_paid_target_cannot_be_paid_again_and_drops_out_of_its_checkout(): void
    {
        $patient = $this->patient();
        $first = $this->order($patient, 350000, 'ORD-1');
        $second = $this->order($patient, 120000, 'ORD-2');

        $reference = $this->start($patient, $first)->json('data.reference');
        $payment = Payment::query()->where('reference', $reference)->firstOrFail();
        app(Settlement::class)->paid($payment, new GatewayVerdict(GatewayVerdict::PAID, 350000, null, null, null));

        $this->start($patient, $first)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ALREADY_PAID');

        $second->siblings = [$first];

        $this->start($patient, $second)
            ->assertCreated()
            ->assertJsonPath('data.amount_kobo', 120000)
            ->assertJsonPath('data.target_ids', [$second->id]);
    }

    public function test_the_module_refuses_a_target_that_can_no_longer_be_paid(): void
    {
        $patient = $this->patient();
        $order = $this->order($patient, 350000);
        $order->refusal = 'ORDER_CANCELLED';

        $this->start($patient, $order)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ORDER_CANCELLED');

        Http::assertNothingSent();
    }

    public function test_someone_elses_target_and_an_unbuilt_purpose_are_not_found(): void
    {
        $patient = $this->patient();
        $order = $this->order($this->patient(), 350000);

        $this->start($patient, $order)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'ORDER_NOT_FOUND');

        $this->actingAs($patient)
            ->postJson('api/v1/payments', ['purpose' => 'lab_booking', 'target_id' => $order->id])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'LAB_BOOKING_NOT_FOUND');
    }

    public function test_a_counter_payment_never_reaches_the_gateway(): void
    {
        $patient = $this->patient();
        $order = $this->order($patient, 350000);

        $this->actingAs($patient)
            ->postJson('api/v1/payments', ['purpose' => 'order', 'target_id' => $order->id, 'channel' => 'counter'])
            ->assertCreated()
            ->assertJsonPath('data.channel', 'counter')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.authorization_url', null);

        Http::assertNothingSent();

        $consultation = new StubPayable(PaymentPurpose::Consultation, $patient, 500000);
        $this->payables->add($consultation);

        $this->actingAs($patient)
            ->postJson('api/v1/payments', ['purpose' => 'consultation', 'target_id' => $consultation->id, 'channel' => 'counter'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'COUNTER_IS_FOR_PICKUP')
            ->assertJsonPath('error.field', 'channel');
    }

    public function test_without_keys_or_a_reachable_gateway_nothing_is_started(): void
    {
        $patient = $this->patient();
        $order = $this->order($patient, 350000);

        config(['services.paystack.secret' => null]);

        $this->start($patient, $order)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'PAYMENTS_UNAVAILABLE');

        config(['services.paystack.secret' => self::SECRET]);
        Http::swap(new Factory);
        Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Invalid key'], 401)]);

        $this->start($patient, $order)
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'PAYMENT_GATEWAY_UNAVAILABLE');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_the_request_is_validated_and_only_patients_pay(): void
    {
        $patient = $this->patient();

        $this->actingAs($patient)
            ->postJson('api/v1/payments', ['purpose' => 'gift', 'target_id' => 'x'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.field', 'purpose');

        $this->actingAs($patient)
            ->postJson('api/v1/payments', ['purpose' => 'order', 'target_id' => 'x'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'target_id');

        $doctor = User::factory()
            ->app(AccountKind::Doctor)
            ->create();

        $this->actingAs($doctor)
            ->postJson('api/v1/payments', ['purpose' => 'order', 'target_id' => $patient->uuid])
            ->assertForbidden();
    }

    private function start(User $patient, StubPayable $target): TestResponse
    {
        return $this->actingAs($patient)->postJson('api/v1/payments', [
            'purpose' => $target->purpose->value,
            'target_id' => $target->id,
        ]);
    }

    private function patient(): User
    {
        return User::factory()
            ->app(AccountKind::Patient)
            ->create();
    }
}
