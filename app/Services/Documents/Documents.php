<?php

namespace App\Services\Documents;

use App\Exceptions\ApiException;
use App\Models\Document;
use App\Models\User;
use App\Services\Audit\AuditLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The terms and privacy documents: read by anyone, edited by the team. The set is the built-in
 * documents the clients link to by slug, so it is read whole.
 */
class Documents
{
    public function __construct(
        private readonly AuditLog $audit,
    ) {}

    public function bySlug(string $slug): Document
    {
        return Document::query()
            ->where('slug', $slug)
            ->first() ?? throw ApiException::notFound(Document::NOT_FOUND);
    }

    /**
     * @return Collection<int, Document>
     */
    public function all(): Collection
    {
        return Document::query()
            ->with('editor')
            ->ordered()
            ->get();
    }

    public function find(string $id): Document
    {
        $document = Document::query()
            ->where('uuid', $id)
            ->first() ?? throw ApiException::notFound(Document::NOT_FOUND);

        return $document->load('editor');
    }

    /**
     * Changes the title or body; a field left out keeps what the document has.
     *
     * @param  array{title?: string, body?: string}  $details
     */
    public function update(User $actor, string $id, array $details): Document
    {
        $document = DB::transaction(function () use ($actor, $id, $details) {
            $document = Document::query()
                ->where('uuid', $id)
                ->lockForUpdate()
                ->first() ?? throw ApiException::notFound(Document::NOT_FOUND);

            $changed = array_keys($details);

            $document->fill([
                ...array_map(trim(...), $details),
                'updated_by' => $actor->id,
            ]);
            $document->save();

            $this->audit->record($actor, 'document.updated', $document, ['slug' => $document->slug, 'fields' => $changed]);

            return $document;
        });

        return $document->load('editor');
    }
}
