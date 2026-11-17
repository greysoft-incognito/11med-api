<?php

namespace App\Models;

use App\Enums\MediaPurpose;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * An uploaded file (app handover §12, portal handover §3). A record points at it by URL;
 * one that no record claims within a day is pruned with its file.
 */
class Media extends Model
{
    use Prunable;

    public const NOT_FOUND = 'MEDIA_NOT_FOUND';

    protected $table = 'media';

    protected $fillable = [
        'user_id',
        'purpose',
        'disk',
        'path',
        'name',
        'content_type',
        'width',
        'height',
        'size',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => MediaPurpose::class,
            'width' => 'integer',
            'height' => 'integer',
            'size' => 'integer',
            'claimed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isImage(): bool
    {
        return MediaPurpose::isImage($this->content_type);
    }

    /**
     * Uploads no record claimed within the configured window.
     */
    public function prunable(): Builder
    {
        return static::withTrashed()
            ->whereNull('claimed_at')
            ->where('created_at', '<', now()->subHours(config('elevenmed.media.orphan_hours')));
    }

    protected function pruning(): void
    {
        Storage::disk($this->disk)->delete($this->path);
    }
}
