<?php

namespace App\Http\Resources\Documents;

use App\Http\Resources\ApiResource;
use App\Models\Document;
use Illuminate\Http\Request;

/**
 * `zDocument` (app and portal `src/api/contracts/documents.ts`).
 *
 * @property Document $resource
 */
class DocumentResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $document = $this->resource;

        return [
            'id' => $document->uuid,
            'slug' => $document->slug,
            'title' => $document->title,
            'body' => $document->body,
            'updated_at' => $this->iso($document->updated_at),
        ];
    }
}
