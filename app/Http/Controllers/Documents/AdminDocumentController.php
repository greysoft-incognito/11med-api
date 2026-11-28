<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Http\Resources\Documents\AdminDocumentCollection;
use App\Http\Resources\Documents\AdminDocumentResource;
use App\Services\Documents\Documents;

/**
 * The team edits the terms and privacy documents the app and the portals show.
 */
class AdminDocumentController extends Controller
{
    public function __construct(
        private readonly Documents $documents,
    ) {}

    public function index(): AdminDocumentCollection
    {
        $documents = $this->documents->all();

        return new AdminDocumentCollection($documents);
    }

    public function show(string $id): AdminDocumentResource
    {
        $document = $this->documents->find($id);

        return new AdminDocumentResource($document);
    }

    public function update(UpdateDocumentRequest $request, string $id): AdminDocumentResource
    {
        $document = $this->documents->update($request->user(), $id, $request->validated());

        return new AdminDocumentResource($document);
    }
}
