<?php

namespace App\Http\Requests\Documents;

use App\Http\Requests\ApiRequest;

/**
 * `zUpdateDocumentRequest` (portal `src/api/contracts/documents.ts`): only the fields sent change.
 */
class UpdateDocumentRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'min:3', 'max:120'],
            'body' => ['sometimes', 'string', 'min:20', 'max:100000'],
        ];
    }
}
