<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * A named set of admin permissions given to team accounts. Stored in Spatie's `roles` table with
 * `is_staff` set; its Spatie name is `staff:<key>` for a seeded default and `staff:<uuid>` otherwise,
 * so renaming one changes only its label.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property ?string $key
 * @property string $label
 * @property ?string $description
 */
class StaffRole extends Role
{
    public const SUPER_ADMIN = 'super-admin';

    /**
     * The guard every role and permission is stored under, whichever guard a request signs in through.
     */
    public const GUARD = 'web';

    protected $casts = [
        'is_staff' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('staff', fn(Builder $query) => $query->where('is_staff', true));

    }

    /**
     * Creates a staff role from its key (a seeded default) or a fresh public id.
     *
     * @param  array{key?: ?string, label: string, description?: ?string}  $attributes
     */
    public static function open(array $attributes): self
    {
        $uuid = (string) Str::uuid7();
        $key = $attributes['key'] ?? null;

        return static::create([
            ...$attributes,
            'uuid' => $uuid,
            'is_staff' => true,
            'guard_name' => self::GUARD,
            'name' => 'staff:' . ($key ?? $uuid),
        ]);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * The team accounts holding the role, whatever guard the request signed in through.
     */
    public function users(): BelongsToMany
    {
        return $this->morphedByMany(
            User::class,
            'model',
            config('permission.table_names.model_has_roles'),
            app(PermissionRegistrar::class)->pivotRole,
            config('permission.column_names.model_morph_key'),
        );
    }

    public function isSuperAdmin(): bool
    {
        return $this->key === self::SUPER_ADMIN;
    }

    public static function superAdminName(): string
    {
        return 'staff:' . self::SUPER_ADMIN;
    }
}
