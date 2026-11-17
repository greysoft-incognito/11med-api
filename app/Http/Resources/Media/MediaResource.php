<?php

namespace App\Http\Resources\Media;

use App\Http\Resources\ApiResource;
use App\Models\Media;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\Request;

/**
 * `zMedia`.
 *
 * @property Media $resource
 */
class MediaResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->uuid,
            'purpose' => $this->resource->purpose->value,
            'url' => app(MediaLibrary::class)->url($this->resource),
            'name' => $this->resource->name,
            'width' => $this->resource->width,
            'height' => $this->resource->height,
            'content_type' => $this->resource->content_type,
            'uploaded_at' => $this->iso($this->resource->created_at),
        ];
    }
}
