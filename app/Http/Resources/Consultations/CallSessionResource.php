<?php

namespace App\Http\Resources\Consultations;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * `zCallSession`: the room and this participant's token, never the provider's key.
 *
 * @property array<string, mixed> $resource
 */
class CallSessionResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $call = $this->resource;

        return [
            'consultation_id' => $call['consultation']->uuid,
            'session_type' => $call['consultation']->session_type->value,
            'provider' => $call['provider'],
            'room' => $call['room'],
            'token' => $call['token'],
            'expires_at' => $this->iso($call['expires_at']),
            'can_share_screen' => $call['can_share_screen'],
            'peer_name' => $call['peer_name'],
        ];
    }
}
