<?php

namespace Tests\Feature\Payments;

use App\Enums\AccountKind;
use App\Enums\PaymentPurpose;
use App\Events\Payments\PaymentAbandoned;
use App\Events\Payments\PaymentRefunded;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\Payments;
use App\Services\Payments\Refunds;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Support\Payments\FakesPaystack;
use Tests\Support\Payments\StubPayable;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use FakesPaystack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakePaystack();
    }

    public function test_a_cancelled_order_is_refunded_its_share_and_its_sibling_stays_paid(): void
    {
        Event::fake([PaymentRefunded::class]);

        [$first, $second, $reference] = $this->paidCheckout();

        app(Refunds::class)->refund($first);
        app(Refunds::class)->refund($first);

        Http::assertSentCount(2);
        Http::assertSent(fn(Request $request) => $request->url() === 'https://api.paystack.co/refund'
            && $request['transaction'] === $reference
            && $request['amount'] === 350000);

        $this->assertSame('paid', $this->summary($first)['status']);

        $this->webhook('refund.processed', ['id' => 359000, 'transaction_reference' => $reference, 'amount' => 350000])->assertOk();

        $this->assertSame('refunded', $this->summary($first)['status']);
        $this->assertSame('paid', $this->summary($second)['status']);
        $this->assertSame('paid', $this->statusOf($reference));

        Event::assertDispatched(PaymentRefunded::class, fn(PaymentRefunded $e) => $e->target === $first
            && $e->reference === $reference
            && $e->amountKobo === 350000);

        app(Refunds::class)->refund($second);
        $this->webhook('refund.processed', ['transaction_reference' => $reference, 'amount' => 120000])->assertOk();

        $this->assertSame('refunded', $this->statusOf($reference));
    }

    public function test_a_refund_paystack_refuses_leaves_the_share_paid_and_tells_the_team(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        [$first, , $reference] = $this->paidCheckout();

        app(Refunds::class)->refund($first);

        $this->webhook('refund.failed', ['id' => 359000, 'transaction_reference' => $reference, 'amount' => 350000])->assertOk();

        $this->assertSame('paid', $this->summary($first)['status']);
        $this->assertDatabaseHas('payment_refunds', ['status' => 'failed']);
        $this->assertDatabaseHas('payment_targets', ['target_id' => $first->id, 'state' => 'refund_failed']);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $admin->id,
            'category' => 'payment_alert',
            'target_id' => Payment::query()->where('reference', $reference)->value('uuid'),
        ]);
    }

    public function test_a_refund_is_never_more_than_the_share(): void
    {
        [$first] = $this->paidCheckout();

        $this->expectExceptionMessage(__('errors.REFUND_EXCEEDS_SHARE'));

        app(Refunds::class)->refund($first, 350001);
    }

    public function test_cancelling_before_payment_releases_the_target(): void
    {
        Event::fake([PaymentAbandoned::class]);

        $patient = $this->patient();
        $first = $this->order($patient, 350000, 'ORD-1');
        $second = $this->order($patient, 120000, 'ORD-2');
        $first->siblings = [$second];
        $reference = $this->start($patient, $first);

        app(Refunds::class)->refund($first);

        $this->assertSame('abandoned', $this->summary($first)['status']);
        $this->assertSame('pending', $this->summary($second)['status']);
        Event::assertDispatchedTimes(PaymentAbandoned::class, 1);

        app(Refunds::class)->refund($second);

        $this->assertSame('abandoned', $this->statusOf($reference));
        Event::assertDispatchedTimes(PaymentAbandoned::class, 2);
        Http::assertNotSent(fn(Request $request) => str_ends_with($request->url(), '/refund'));
    }

    public function test_a_consultation_is_refunded_in_full(): void
    {
        $patient = $this->patient();
        $consultation = new StubPayable(PaymentPurpose::Consultation, $patient, 500000, 'consult');
        $this->payables->add($consultation);

        $reference = $this->start($patient, $consultation);
        $this->charged($reference, 500000)->assertOk();

        app(Refunds::class)->refund($consultation);
        $this->webhook('refund.processed', ['transaction_reference' => $reference, 'amount' => 500000])->assertOk();

        $this->assertSame('refunded', $this->statusOf($reference));
    }

    /**
     * @return array{StubPayable, StubPayable, string}
     */
    private function paidCheckout(): array
    {
        $patient = $this->patient();
        $first = $this->order($patient, 350000, 'ORD-1');
        $second = $this->order($patient, 120000, 'ORD-2');
        $first->siblings = [$second];

        $reference = $this->start($patient, $first);
        $this->charged($reference, 470000)->assertOk();

        return [$first, $second, $reference];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(StubPayable $target): array
    {
        return app(Payments::class)->summaryFor($target);
    }

    private function start(User $patient, StubPayable $target): string
    {
        return $this->actingAs($patient)
            ->postJson('api/v1/payments', ['purpose' => $target->purpose->value, 'target_id' => $target->id])
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
