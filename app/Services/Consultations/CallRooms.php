<?php

namespace App\Services\Consultations;

use App\Exceptions\ApiException;
use App\Models\Consultation;
use App\Models\User;
use App\Services\Consultations\Calls\CallProvider;
use App\Services\Consultations\Calls\CallProviders;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Rooms and tokens for audio and video consultations (app `zCallSession`, handover §15): one room per
 * consultation, made the first time either side asks, a fresh token per ask, removed when it ends.
 */
class CallRooms
{
    public function __construct(
        private readonly CallProviders $providers,
    ) {}

    /**
     * @return array{consultation: Consultation, provider: string, room: string, token: string, expires_at: CarbonImmutable, can_share_screen: bool, peer_name: string}
     */
    public function join(Consultation $consultation, User $participant, bool $isDoctor): array
    {
        if (! $consultation->session_type->isCall()) {
            throw ApiException::unprocessable('NOT_A_CALL');
        }

        if ($consultation->status->value !== 'in_progress') {
            throw ApiException::unprocessable('CALL_NOT_OPEN');
        }

        $expiresAt = CarbonImmutable::now()->addMinutes($consultation->length_minutes + config('elevenmed.calls.token_margin_minutes'));
        $room = $this->room($consultation, $expiresAt);
        $token = $this->provider()->token($room, $participant->uuid, $isDoctor, $expiresAt);
        $peer = $isDoctor ? $consultation->patient->full_name : $consultation->doctor_name;

        return [
            'consultation' => $consultation,
            'provider' => $this->provider()->name()->value,
            'room' => $room,
            'token' => $token,
            'expires_at' => $expiresAt,
            'can_share_screen' => $isDoctor,
            'peer_name' => $peer,
        ];
    }

    public function close(Consultation $consultation): void
    {
        $room = $consultation->call_room;

        if ($room === null) {
            return;
        }

        DB::afterCommit(fn() => $this->provider()->deleteRoom($room));
    }

    private function provider(): CallProvider
    {
        return $this->providers->current();
    }

    private function room(Consultation $consultation, CarbonImmutable $expiresAt): string
    {
        return DB::transaction(function () use ($consultation, $expiresAt) {
            $current = Consultation::query()
                ->whereKey($consultation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($current->call_room === null) {
                $current->call_provider = $this->provider()->name()->value;
                $current->call_room = $this->provider()->createRoom($current, $expiresAt);
                $current->save();
            }

            return $current->call_room;
        });
    }
}
