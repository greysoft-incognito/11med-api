<?php

namespace App\Services\Payments\Gateway;

use App\Enums\PaymentChannel;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The gateway's account of a transaction, from a webhook or a verify call. `outcome` is null while
 * it is still open.
 */
final readonly class GatewayVerdict
{
    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const ABANDONED = 'abandoned';

    public function __construct(
        public ?string $outcome,
        public int $amountKobo,
        public ?PaymentChannel $channel,
        public ?string $gatewayResponse,
        public ?CarbonInterface $paidAt,
    ) {}

    /**
     * Paystack's transaction object, as a webhook or a verify call carries it.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPaystack(array $data): self
    {
        $outcome = match ($data['status'] ?? null) {
            'success' => self::PAID,
            'failed', 'reversed' => self::FAILED,
            'abandoned' => self::ABANDONED,
            default => null,
        };

        $paidAt = isset($data['paid_at']) || isset($data['paidAt'])
            ? Carbon::parse($data['paid_at'] ?? $data['paidAt'])
            : null;

        return new self(
            $outcome,
            (int) ($data['amount'] ?? 0),
            PaymentChannel::fromGateway($data['channel'] ?? null),
            isset($data['gateway_response']) ? mb_substr((string) $data['gateway_response'], 0, 255) : null,
            $paidAt,
        );
    }
}
