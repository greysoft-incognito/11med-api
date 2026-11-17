<?php

namespace Tests\Support\Payments;

use App\Enums\PaymentPurpose;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * Paystack's HTTP faked, a stub module bound for each purpose, and signed webhook calls.
 */
trait FakesPaystack
{
    protected const SECRET = 'sk_test_secret';

    protected StubPayables $payables;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $verdicts = [];

    protected function fakePaystack(): void
    {
        config(['services.paystack.secret' => self::SECRET]);

        $this->payables = new StubPayables;

        foreach ([PaymentPurpose::Order, PaymentPurpose::Consultation] as $purpose) {
            $this->app->instance($purpose->sourceClass(), $this->payables);
        }

        Http::fake([
            'api.paystack.co/transaction/initialize' => fn($request) => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/' . $request['reference'],
                    'access_code' => 'ac_' . $request['reference'],
                    'reference' => $request['reference'],
                ],
            ]),
            'api.paystack.co/transaction/verify/*' => function ($request) {
                $reference = rawurldecode(basename($request->url()));

                return Http::response(['status' => true, 'data' => $this->verdicts[$reference] ?? ['status' => 'ongoing', 'amount' => 0]]);
            },
            'api.paystack.co/refund' => fn($request) => Http::response([
                'status' => true,
                'data' => ['id' => 9000 + (int) $request['amount'], 'status' => 'pending'],
            ]),
        ]);
    }

    protected function order(User $patient, int $amount, string $reference = 'ORD-0001'): StubPayable
    {
        $order = new StubPayable(PaymentPurpose::Order, $patient, $amount, $reference);

        $this->payables->add($order);

        return $order;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function webhook(string $event, array $data, ?string $secret = self::SECRET): TestResponse
    {
        $body = json_encode(['event' => $event, 'data' => $data]);

        return $this->call(
            'POST',
            'api/v1/payments/webhook/paystack',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, (string) $secret),
            ],
            $body,
        );
    }

    protected function charged(string $reference, int $amount, string $channel = 'card'): TestResponse
    {
        return $this->webhook('charge.success', [
            'reference' => $reference,
            'status' => 'success',
            'amount' => $amount,
            'channel' => $channel,
            'gateway_response' => 'Successful',
            'paid_at' => '2026-11-17T10:00:00.000Z',
        ]);
    }
}
