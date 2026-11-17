<?php

namespace App\Services\Consultations\Calls;

use App\Enums\CallProviderName;
use App\Models\Consultation;
use Carbon\CarbonInterface;

/**
 * A call provider (app handover §15): it carries the media, the server only makes rooms and tokens.
 * The provider's key never leaves the server.
 */
interface CallProvider
{
    public function name(): CallProviderName;

    /**
     * Makes the consultation's room: a URL for Daily, a channel name for Agora.
     */
    public function createRoom(Consultation $consultation, CarbonInterface $expiresAt): string;

    /**
     * A token for one participant of one room, lapsing at `$expiresAt`.
     */
    public function token(string $room, string $participantId, bool $canShareScreen, CarbonInterface $expiresAt): string;

    public function deleteRoom(string $room): void;
}
