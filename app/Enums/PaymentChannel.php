<?php

namespace App\Enums;

/**
 * How a payment was made (app `zPaymentChannel`). `counter` is a pickup paid when collected.
 */
enum PaymentChannel: string
{
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case Ussd = 'ussd';
    case Counter = 'counter';
}
