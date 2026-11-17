<?php

namespace App\Services\Consultations\Calls;

use App\Enums\CallProviderName;
use App\Exceptions\ApiException;

/**
 * The call provider `elevenmed.calls.provider` names (HT-008).
 */
class CallProviders
{
    public function current(): CallProvider
    {
        return match (CallProviderName::tryFrom((string) config('elevenmed.calls.provider'))) {
            CallProviderName::Mock => new MockCallProvider,
            CallProviderName::Daily => new DailyCallProvider,
            default => throw ApiException::unprocessable('CALL_UNAVAILABLE'),
        };
    }
}
