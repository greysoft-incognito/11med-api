<?php

namespace App\Http\Resources;

use App\Support\Envelope;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Toneflix\ResourceModifier\Services\Json\JsonResource;

/**
 * Base for every 11med resource: the body is `data` inside the clients' success envelope,
 * with snake_case keys exactly as the zod contracts spell them.
 */
abstract class ApiResource extends JsonResource
{
    public static $wrap = 'data';

    protected string $message = 'OK';

    public function withMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return Envelope::successKeys($this->message);
    }

    /**
     * An instant: ISO 8601 in UTC with milliseconds.
     */
    protected function iso(DateTimeInterface|string|null $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $carbon = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        return $carbon
            ->copy()
            ->utc()
            ->toIso8601ZuluString('millisecond');
    }

    /**
     * A calendar date: `YYYY-MM-DD`.
     */
    protected function day(DateTimeInterface|string|null $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return ($date instanceof CarbonInterface ? $date : Carbon::parse($date))->toDateString();
    }
}
