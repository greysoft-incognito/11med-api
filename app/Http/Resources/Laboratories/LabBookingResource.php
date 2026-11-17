<?php

namespace App\Http\Resources\Laboratories;

use App\Http\Resources\ApiResource;
use App\Models\LabBooking;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\Request;

/**
 * App `zLabBooking`. The results link is signed and short-lived, made fresh on each read.
 *
 * @property LabBooking $resource
 */
class LabBookingResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $booking = $this->resource;

        return [
            ...self::shared($booking),
            'laboratory_id' => $booking->laboratory->organisation->uuid,
            'laboratory_name' => $booking->laboratory->organisation->name,
            'request_id' => $booking->request?->uuid,
        ];
    }

    /**
     * The fields the app's and the portal's bookings have in common.
     *
     * @return array<string, mixed>
     */
    public static function shared(LabBooking $booking): array
    {
        return [
            'id' => $booking->uuid,
            'reference' => $booking->reference,
            'tests' => array_map(fn(array $test) => [
                'test_code' => $test['test_code'],
                'test_name' => $test['test_name'],
                'price_kobo' => (int) $test['price_kobo'],
            ], $booking->tests),
            'tests_kobo' => $booking->tests_kobo,
            'collection' => $booking->collection->value,
            'collection_fee_kobo' => $booking->collection_fee_kobo,
            'total_kobo' => $booking->total_kobo,
            'address' => $booking->address,
            'scheduled_for' => self::instant($booking->scheduled_for),
            'fasting_hours' => $booking->fasting_hours,
            'status' => $booking->status->value,
            'booked_at' => self::instant($booking->booked_at),
            'payment' => $booking->paymentSummary,
            'results_url' => $booking->results === null ? null : app(MediaLibrary::class)->url($booking->results),
            'results_at' => self::instant($booking->results_at),
        ];
    }

    private static function instant(mixed $date): ?string
    {
        return $date?->copy()->utc()->toIso8601ZuluString('millisecond');
    }
}
