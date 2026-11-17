<?php

namespace App\Services\Payments\Gateway;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Paystack's REST API: initialise, verify and refund, and the webhook signature (HMAC SHA-512 of the
 * raw body with the secret key).
 */
class PaystackGateway implements PaymentGateway
{
    public function configured(): bool
    {
        return filled(config('services.paystack.secret'));
    }

    public function initialise(string $reference, int $amountKobo, string $email, array $channels, string $callbackUrl, array $metadata): GatewayCheckout
    {
        $payload = array_filter([
            'reference' => $reference,
            'amount' => $amountKobo,
            'currency' => 'NGN',
            'email' => $email,
            'channels' => $channels ?: null,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ], fn($value) => $value !== null);

        $data = $this->call(fn(PendingRequest $http) => $http->post('transaction/initialize', $payload));

        if (! is_string($data['authorization_url'] ?? null)) {
            throw new GatewayUnavailable('Paystack returned no authorization URL.');
        }

        return new GatewayCheckout($data['authorization_url'], $data['access_code'] ?? null);
    }

    public function verify(string $reference): GatewayVerdict
    {
        $data = $this->call(fn(PendingRequest $http) => $http->get('transaction/verify/' . rawurlencode($reference)));

        return GatewayVerdict::fromPaystack($data);
    }

    public function refund(string $reference, int $amountKobo): string
    {
        $data = $this->call(fn(PendingRequest $http) => $http->post('refund', [
            'transaction' => $reference,
            'amount' => $amountKobo,
        ]));

        return (string) ($data['id'] ?? '');
    }

    public function signatureIsValid(string $payload, ?string $signature): bool
    {
        $secret = (string) config('services.paystack.secret');

        if ($secret === '' || $signature === null || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $payload, $secret), $signature);
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     * @return array<string, mixed>
     */
    private function call(callable $request): array
    {
        if (! $this->configured()) {
            throw new GatewayUnavailable('Paystack is not configured.');
        }

        $http = Http::baseUrl(config('services.paystack.base_url'))
            ->withToken(config('services.paystack.secret'))
            ->acceptJson()
            ->timeout(15);

        try {
            $response = $request($http);
        } catch (ConnectionException $e) {
            throw new GatewayUnavailable($e->getMessage(), 0, $e);
        }

        if ($response->failed() || $response->json('status') !== true) {
            throw new GatewayUnavailable('Paystack refused the call: ' . $response->status());
        }

        return (array) $response->json('data', []);
    }
}
