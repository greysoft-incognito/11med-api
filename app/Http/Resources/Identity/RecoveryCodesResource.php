<?php

namespace App\Http\Resources\Identity;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * `zRecoveryCodes`, shown once.
 *
 * @property list<string> $resource
 */
class RecoveryCodesResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'recovery_codes' => $this->resource,
        ];
    }
}
