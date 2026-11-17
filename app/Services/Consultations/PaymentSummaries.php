<?php

namespace App\Services\Consultations;

use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Consultation;
use App\Services\Payments\Payments;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Collection;

/**
 * The `zPaymentSummary` each consultation carries, read once per list (`prime`). A free booking
 * never reached the gateway and reads paid at booking.
 */
#[Scoped]
class PaymentSummaries
{
    /** @var array<string, array<string, mixed>|null> */
    private array $known = [];

    public function __construct(
        private readonly Payments $payments,
    ) {}

    /**
     * @param  Collection<int, Consultation>  $consultations
     */
    public function prime(Collection $consultations): void
    {
        $ids = $consultations
            ->pluck('uuid')
            ->reject(fn(string $id) => array_key_exists($id, $this->known))
            ->values()
            ->all();

        if ($ids === []) {
            return;
        }

        $found = $this->payments->summariesFor(PaymentPurpose::Consultation, $ids);

        foreach ($ids as $id) {
            $this->known[$id] = $found[$id] ?? null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function of(Consultation $consultation): array
    {
        if (! array_key_exists($consultation->uuid, $this->known)) {
            $this->known[$consultation->uuid] = $this->payments->summaryFor($consultation);
        }

        return $this->known[$consultation->uuid] ?? $this->unstarted($consultation);
    }

    /**
     * @return array<string, mixed>
     */
    private function unstarted(Consultation $consultation): array
    {
        $paid = $consultation->paid_at !== null;

        return [
            'reference' => $consultation->uuid,
            'status' => ($paid ? PaymentStatus::Paid : PaymentStatus::Pending)->value,
            'channel' => null,
            'amount_kobo' => $consultation->fee_kobo,
            'authorization_url' => null,
            'paid_at' => $consultation->paid_at?->copy()->utc()->toIso8601ZuluString('millisecond'),
        ];
    }
}
