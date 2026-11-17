<?php

namespace App\Services\Consultations\Calls;

use App\Enums\CallProviderName;
use App\Models\Consultation;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Made-up rooms and tokens the app's stand-in adapter opens without carrying media (app `mockCallSession`).
 */
class MockCallProvider implements CallProvider
{
    public function name(): CallProviderName
    {
        return CallProviderName::Mock;
    }

    public function createRoom(Consultation $consultation, CarbonInterface $expiresAt): string
    {
        return "mock:{$consultation->uuid}";
    }

    public function token(string $room, string $participantId, bool $canShareScreen, CarbonInterface $expiresAt): string
    {
        return 'mock.' . $participantId . '.' . Str::random(16);
    }

    public function deleteRoom(string $room): void {}
}
