<?php

namespace App\Http\Resources\Accounts;

use App\Http\Resources\ApiResource;
use App\Services\Identity\CodeSent;
use Illuminate\Http\Request;

/**
 * `zAccountCodeSent`: a code went to the person, never shown to the team.
 *
 * @property CodeSent $resource
 */
class AccountCodeSentResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'expires_in' => $this->resource->expiresIn,
            'resends_remaining' => $this->resource->resendsRemaining,
        ];
    }
}
