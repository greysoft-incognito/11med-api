<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A terms or privacy document the clients show and accept, its body in Markdown. The app reads
 * `terms` and `privacy`, the partner portals `partner-terms` and `partner-privacy`. Every edit is a
 * new `version`, kept in `document_versions`.
 */
class Document extends Model
{
    public const NOT_FOUND = 'DOCUMENT_NOT_FOUND';

    public const TERMS = 'terms';

    public const PRIVACY = 'privacy';

    public const PARTNER_TERMS = 'partner-terms';

    public const PARTNER_PRIVACY = 'partner-privacy';

    public const BUILT_IN = [self::TERMS, self::PRIVACY, self::PARTNER_TERMS, self::PARTNER_PRIVACY];

    /** The pair each kind of account accepts, in the order its policy version names them. */
    public const APP = [self::TERMS, self::PRIVACY];

    public const PARTNER = [self::PARTNER_TERMS, self::PARTNER_PRIVACY];

    protected $fillable = [
        'slug',
        'title',
        'body',
        'version',
        'sort',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'sort' => 'integer',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query
            ->orderBy('sort')
            ->orderBy('id');
    }
}
