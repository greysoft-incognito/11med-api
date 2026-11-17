<?php

namespace App\Services\Media;

/**
 * A file that passed inspection: the bytes to store and what they are.
 */
final readonly class InspectedFile
{
    public function __construct(
        public string $contents,
        public string $contentType,
        public string $extension,
        public ?int $width = null,
        public ?int $height = null,
    ) {}
}
