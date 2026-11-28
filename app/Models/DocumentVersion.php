<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One published version of a terms or privacy document, written once and never changed.
 */
class DocumentVersion extends Model
{
    protected $fillable = [
        'document_id',
        'version',
        'title',
        'body',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
