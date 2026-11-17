<?php

namespace App\Http\Resources\Identity;

use App\Http\Resources\ApiResource;
use App\Services\Identity\Social\SocialOutcome;
use Illuminate\Http\Request;

/**
 * `zSocialAuthResponse`.
 *
 * @property SocialOutcome $resource
 */
class SocialOutcomeResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $session = $this->resource->session;

        if ($session !== null) {
            return [
                'status' => 'authenticated',
                'session' => new AppSessionResource($session),
            ];
        }

        return [
            'status' => 'phone_required',
            'link_token' => $this->resource->linkToken,
            'suggested_full_name' => $this->resource->suggestedFullName,
        ];
    }
}
