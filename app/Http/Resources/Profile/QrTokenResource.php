<?php

namespace App\Http\Resources\Profile;

use App\Http\Resources\ApiResource;
use App\Models\QrToken;
use App\Services\Profile\QrCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `zQrToken`.
 *
 * @property QrToken $resource
 */
class QrTokenResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource->token,
            'url' => app(QrCodes::class)->url($this->resource),
            'issued_at' => $this->iso($this->resource->issued_at),
            'last_accessed_at' => $this->iso($this->resource->last_opened_at),
        ];
    }

    /**
     * Reading or replacing the code answers 200, even when the token was made by this request.
     */
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(200);
    }
}
