<?php

namespace App\Services\Media;

use App\Enums\HttpStatus;
use App\Enums\MediaPurpose;
use App\Exceptions\ApiException;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Decides what an upload really is from its bytes, never from the client's word, and
 * produces what may be stored: a picture re-encoded from its pixels, so no metadata
 * survives (app handover §3.5), or a PDF without active content.
 */
class UploadInspector
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    /**
     * Names that let a PDF run something or carry another file inside it.
     */
    private const ACTIVE_PDF = '~/(JavaScript|JS|Launch|EmbeddedFile|RichMedia)(?![A-Za-z])~';

    /**
     * @param  array{width?: int|null, height?: int|null, size?: int|null}  $claims
     */
    public function inspect(MediaPurpose $purpose, mixed $file, array $claims): InspectedFile
    {
        $file = $this->received($file, $claims['size'] ?? null);
        $type = $file->getMimeType();

        if (! in_array($type, $purpose->contentTypes(), true)) {
            throw $this->unsupported();
        }

        $contents = (string) file_get_contents($file->getRealPath());

        return MediaPurpose::isImage($type)
            ? $this->picture($contents, $type, $claims)
            : $this->document($contents, $type);
    }

    private function received(mixed $file, ?int $declaredSize): UploadedFile
    {
        $limit = config('elevenmed.media.max_bytes');

        if (! $file instanceof UploadedFile) {
            throw ApiException::badRequest('NO_FILE', 'file');
        }

        if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw $this->tooLarge();
        }

        if (! $file->isValid() || $file->getSize() === 0) {
            throw ApiException::badRequest('NO_FILE', 'file');
        }

        if ($file->getSize() > $limit || ($declaredSize !== null && $declaredSize > $limit)) {
            throw $this->tooLarge();
        }

        return $file;
    }

    /**
     * @param  array{width?: int|null, height?: int|null}  $claims
     */
    private function picture(string $contents, string $type, array $claims): InspectedFile
    {
        $size = @getimagesizefromstring($contents);
        $maxEdge = config('elevenmed.media.max_edge');

        if ($size === false || $size['mime'] !== $type) {
            throw $this->unsupported();
        }

        [$width, $height] = $size;
        $claimedWidth = $claims['width'] ?? null;
        $claimedHeight = $claims['height'] ?? null;

        // Checked from the header before any pixels are decoded.
        if (max($width, $height) > $maxEdge || $claimedWidth !== $width || $claimedHeight !== $height) {
            throw ApiException::unprocessable('IMAGE_NOT_RESIZED', 'file');
        }

        try {
            $image = ImageManager::gd(strip: true)->read($contents);

            $encoded = match ($type) {
                'image/png' => $image->toPng(),
                'image/webp' => $image->toWebp(quality: 85),
                default => $image->toJpeg(quality: 85),
            };
        } catch (Throwable) {
            throw $this->unsupported();
        }

        return new InspectedFile($encoded->toString(), $type, self::EXTENSIONS[$type], $image->width(), $image->height());
    }

    private function document(string $contents, string $type): InspectedFile
    {
        if (! str_starts_with($contents, '%PDF-') || preg_match(self::ACTIVE_PDF, $contents) === 1) {
            throw $this->unsupported();
        }

        return new InspectedFile($contents, $type, self::EXTENSIONS[$type]);
    }

    private function unsupported(): ApiException
    {
        return new ApiException(HttpStatus::UNSUPPORTED_MEDIA_TYPE, 'UNSUPPORTED_MEDIA_TYPE', null, 'file');
    }

    private function tooLarge(): ApiException
    {
        return new ApiException(HttpStatus::PAYLOAD_TOO_LARGE, 'FILE_TOO_LARGE', null, 'file');
    }
}
