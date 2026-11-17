<?php

namespace App\Jobs\Payments;

use App\Models\PaymentRefund;
use App\Services\Payments\Refunds;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Asks the gateway for a recorded refund. Retried with backoff; the sweep asks again for any still unasked.
 */
class RequestRefund implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(
        public readonly PaymentRefund $refund,
    ) {}

    public function handle(Refunds $refunds): void
    {
        $refunds->request($this->refund);
    }
}
