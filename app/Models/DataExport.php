<?php

namespace App\Models;

use App\Enums\DataExportStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A copy of an account's data. Not soft deleted: the file is the person's data, and it is
 * removed for real with the row once it lapses.
 */
class DataExport extends Eloquent
{
    use HasPublicId;
    use Prunable;

    public const NOT_FOUND = 'DATA_EXPORT_NOT_FOUND';

    protected $fillable = [
        'user_id',
        'status',
        'path',
        'requested_at',
        'ready_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DataExportStatus::class,
            'requested_at' => 'datetime',
            'ready_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDownloadable(): bool
    {
        return $this->status === DataExportStatus::Ready && $this->expires_at?->isFuture() === true && $this->path !== null;
    }

    public function prunable(): Builder
    {
        return static::query()
            ->where('expires_at', '<', now())
            ->orWhere(fn(Builder $query) => $query->whereNull('expires_at')->where('requested_at', '<', now()->subDays(2)));
    }

    protected function pruning(): void
    {
        if ($this->path !== null) {
            Storage::disk(config('elevenmed.data_export.disk'))->delete($this->path);
        }
    }
}
