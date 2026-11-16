<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * A time-ordered UUID in the `uuid` column: the id the clients see and the route key.
 * The bigint `id` stays internal for joins and the kit's morph relations.
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function (self $model): void {
            $model->uuid ??= (string) Str::uuid7();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
