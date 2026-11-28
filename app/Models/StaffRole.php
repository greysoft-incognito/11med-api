<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * A named set of admin permissions given to team members. Spatie's `roles` table holds staff roles
 * and nothing else (account kinds are `account_kinds`); a role's Spatie name is its key for a seeded
 * default and its public id otherwise, so renaming one changes only its label.
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
            'guard_name' => self::GUARD,
            'name' => $key ?? $uuid,
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
        return self::SUPER_ADMIN;
    }
}
