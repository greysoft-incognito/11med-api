<?php

namespace App\Http\Controllers\Media;

use App\Enums\HttpStatus;
use App\Enums\MediaPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\UploadMediaRequest;
use App\Http\Resources\Media\MediaResource;
use App\Models\Media;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function __construct(
        private readonly MediaLibrary $library,
    ) {}

    /**
     * Signed in for every purpose but a partner's licence, so the account is read, not required.
     */
    public function store(UploadMediaRequest $request): JsonResponse
    {
        $purpose = MediaPurpose::from($request->validated('purpose'));
        $file = $request->file('file');
        $claims = $request->claims();

        $media = $this->library->upload($request->user('sanctum'), $purpose, $file, $claims);

        return (new MediaResource($media))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }

    /**
     * A private file, behind a signed link that lapses (the route checks the signature).
     */
    public function file(Media $media): StreamedResponse
    {
        return $this->library->serve($media);
    }
}
