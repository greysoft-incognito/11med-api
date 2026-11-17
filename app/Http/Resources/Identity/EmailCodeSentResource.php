<?php

namespace App\Http\Resources\Identity;

use App\Http\Resources\ApiResource;
use App\Services\Identity\CodeSent;
use Illuminate\Http\Request;

/**
 * `zEmailCodeSent`.
 *
 * @property CodeSent $resource
 */
class EmailCodeSentResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'resends_remaining' => $this->resource->resendsRemaining,
        ];
    }
}
