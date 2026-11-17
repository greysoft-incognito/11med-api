<?php

namespace App\Enums;

/**
 * How a payment is made (app `zPaymentChannel`). `counter` is a pickup paid at the pharmacy and never
 * reaches the gateway.
 */
enum PaymentChannel: string
{
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case Ussd = 'ussd';
    case Counter = 'counter';

    /**
     * The channel Paystack reports on a charge, or null when it is one the app has no name for.
     */
    public static function fromGateway(?string $channel): ?self
    {
        return match ($channel) {
            'card' => self::Card,
            'bank_transfer', 'bank' => self::BankTransfer,
            'ussd' => self::Ussd,
            default => null,
        };
    }
}
