<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * `zAppVersion`: per platform, the oldest build still served and where to get a newer one.
 *
 * @property array<string, array{minimum: string, update_url: string}> $resource
 */
class AppVersionResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return collect($this->resource)
            ->map(fn(array $platform) => [
                'minimum_version' => $platform['minimum'],
                'update_url' => $platform['update_url'],
            ])
            ->all();
    }
}
