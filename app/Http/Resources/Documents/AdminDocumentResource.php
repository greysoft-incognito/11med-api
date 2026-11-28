<?php

namespace App\Http\Resources\Documents;

use App\Models\Document;
use Illuminate\Http\Request;

/**
 * Portal `zAdminDocument`: the document and who last changed it.
 *
 * @property Document $resource
 */
class AdminDocumentResource extends DocumentResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'updated_by_name' => $this->resource->editor?->full_name,
        ];
    }
}
