<?php

namespace App\Http\Requests\Media;

use App\Enums\MediaPurpose;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * `zUploadMediaRequest` (app and portal `src/api/contracts/media.ts`), multipart. The file
 * part is judged by the inspector, which answers with the contract's own codes.
 */
class UploadMediaRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'purpose' => ['required', Rule::enum(MediaPurpose::class)],
            'width' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'height' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'size' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array{width: int|null, height: int|null, size: int|null}
     */
    public function claims(): array
    {
        return [
            'width' => $this->integerOrNull('width'),
            'height' => $this->integerOrNull('height'),
            'size' => $this->integerOrNull('size'),
        ];
    }

    private function integerOrNull(string $key): ?int
    {
        return $this->filled($key) ? $this->integer($key) : null;
    }
}
