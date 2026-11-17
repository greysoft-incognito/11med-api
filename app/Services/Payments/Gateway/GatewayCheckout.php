<?php

namespace App\Services\Payments\Gateway;

/**
 * Where the patient pays: the hosted page and its access code.
 */
final readonly class GatewayCheckout
{
    public function __construct(
        public string $authorizationUrl,
        public ?string $accessCode,
    ) {}
}
