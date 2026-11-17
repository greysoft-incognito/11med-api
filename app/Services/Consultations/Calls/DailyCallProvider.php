<?php

namespace App\Services\Consultations\Calls;

use App\Enums\CallProviderName;
use App\Exceptions\ApiException;
use App\Models\Consultation;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Daily.co's REST API: a private room per consultation, a meeting token per participant, no recording.
 */
class DailyCallProvider implements CallProvider
{
    public function name(): CallProviderName
    {
        return CallProviderName::Daily;
    }

    public function createRoom(Consultation $consultation, CarbonInterface $expiresAt): string
    {
        $response = $this
            ->client()
            ->post('rooms', [
                'name' => "consultation-{$consultation->uuid}",
                'privacy' => 'private',
                'properties' => [
                    'exp' => $expiresAt->getTimestamp(),
                    'enable_recording' => false,
                    'enable_screenshare' => true,
                    'start_video_off' => $consultation->session_type->value === 'audio',
                ],
            ]);

        if (! $response->successful()) {
            throw ApiException::unprocessable('CALL_UNAVAILABLE');
        }

        return (string) $response->json('url');
    }

    public function token(string $room, string $participantId, bool $canShareScreen, CarbonInterface $expiresAt): string
    {
        $response = $this
            ->client()
            ->post('meeting-tokens', [
                'properties' => [
                    'room_name' => $this->roomName($room),
                    'user_id' => $participantId,
                    'exp' => $expiresAt->getTimestamp(),
                    'enable_screenshare' => $canShareScreen,
                    'enable_recording' => false,
                ],
            ]);

        if (! $response->successful()) {
            throw ApiException::unprocessable('CALL_UNAVAILABLE');
        }

        return (string) $response->json('token');
    }

    public function deleteRoom(string $room): void
    {
        try {
            $this
                ->client()
                ->delete('rooms/' . $this->roomName($room));
        } catch (Throwable) {
            // The room lapses at its own expiry when Daily cannot be reached now.
        }
    }

    private function roomName(string $room): string
    {
        return basename((string) parse_url($room, PHP_URL_PATH));
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('elevenmed.calls.daily.api_url'))
            ->withToken((string) config('elevenmed.calls.daily.api_key'))
            ->acceptJson()
            ->timeout(10);
    }
}
