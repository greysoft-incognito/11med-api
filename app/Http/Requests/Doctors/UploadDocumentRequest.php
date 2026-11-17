<?php

namespace App\Http\Requests\Doctors;

use App\Enums\DocumentKind;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zUploadDocumentRequest`. `file.uri` is the URL of the doctor's own `doctor_document` upload (FE-080).
 */
class UploadDocumentRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(DocumentKind::class)],
            'file' => ['required', 'array'],
            'file.uri' => ['required', 'string', 'max:2048'],
            'file.width' => ['required', 'integer', 'min:1'],
            'file.height' => ['required', 'integer', 'min:1'],
        ];
    }
}
