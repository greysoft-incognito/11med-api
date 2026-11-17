<?php

namespace App\Services\Payments\Gateway;

use Illuminate\Container\Attributes\Bind;

/**
 * The payment gateway's four calls. Paystack in every environment; tests fake its HTTP.
 */
#[Bind(PaystackGateway::class)]
interface PaymentGateway
{
    public function configured(): bool;

    /**
     * Opens a hosted checkout for a reference the server chose.
     *
     * @param  list<string>  $channels
     * @param  array<string, mixed>  $metadata
     *
     * @throws GatewayUnavailable
     */
    public function initialise(string $reference, int $amountKobo, string $email, array $channels, string $callbackUrl, array $metadata): GatewayCheckout;

    /**
     * @throws GatewayUnavailable
     */
    public function verify(string $reference): GatewayVerdict;

    /**
     * @throws GatewayUnavailable
     */
    public function refund(string $reference, int $amountKobo): string;

    public function signatureIsValid(string $payload, ?string $signature): bool;
}
