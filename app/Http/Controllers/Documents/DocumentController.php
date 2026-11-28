<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Resources\Documents\DocumentResource;
use App\Services\Documents\Documents;

/**
 * The terms and privacy documents, open to everyone, signed in or not.
 */
class DocumentController extends Controller
{
    public function __construct(
        private readonly Documents $documents,
    ) {}

    public function show(string $slug): DocumentResource
    {
        $document = $this->documents->bySlug($slug);

        return new DocumentResource($document);
    }
}
