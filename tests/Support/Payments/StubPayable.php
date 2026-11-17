<?php

namespace Tests\Support\Payments;

use App\Enums\PaymentPurpose;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Payments\Payable;
use Illuminate\Support\Str;

/**
 * A payable target with no table behind it, standing in for an order, booking or consultation.
 */
class StubPayable implements Payable
{
    public string $id;

    public ?string $refusal = null;

    /**
     * @var list<StubPayable>
     */
    public array $siblings = [];

    public function __construct(
        public PaymentPurpose $purpose,
        public User $patient,
        public int $amount,
        public string $reference = 'ORD-0001',
        public ?string $partner = 'Medplus Lekki',
    ) {
        $this->id = (string) Str::uuid7();
    }

    public function paymentPurpose(): PaymentPurpose
    {
        return $this->purpose;
    }

    public function paymentTargetId(): string
    {
        return $this->id;
    }

    public function payer(): User
    {
        return $this->patient;
    }

    public function amountKobo(): int
    {
        return $this->amount;
    }

    public function paymentReference(): string
    {
        return $this->reference;
    }

    public function paymentPartnerName(): ?string
    {
        return $this->partner;
    }

    public function assertPayable(): void
    {
        if ($this->refusal !== null) {
            throw ApiException::unprocessable($this->refusal);
        }
    }
}
