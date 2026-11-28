<?php

namespace App\Services\Staff;

use App\Enums\AdminPermission;
use App\Enums\Role;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\StaffRole;
use App\Models\User;
use App\Services\Audit\AuditLog;
use App\Services\Identity\PortalAccounts;
use App\Services\Identity\Sessions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The team's accounts: invited by email, given roles and direct permissions, deactivated and
 * reactivated. Someone always remains an active super admin.
 */
class StaffAccounts
{
    public function __construct(
        private readonly StaffAccess $access,
        private readonly StaffCatalogue $catalogue,
        private readonly PortalAccounts $accounts,
        private readonly Sessions $sessions,
        private readonly AuditLog $audit,
    ) {}

    /**
     * @param  array{q?: ?string, status?: ?string, role?: ?string}  $filters
     */
    public function list(array $filters, ListQuery $list): Page
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? null;

        $query = $this->team()
            ->with(['roles', 'permissions'])
            ->when($q !== '', function (Builder $query) use ($q) {
                $term = '%' . addcslashes($q, '%_\\') . '%';

                $query->where(fn(Builder $match) => $match
                    ->where('full_name', 'ilike', $term)
                    ->orWhere('email', 'ilike', $term));
            })
            ->when($status === 'invited', fn(Builder $query) => $query
                ->whereNull('deactivated_at')
                ->whereNull('password'))
            ->when($status === 'active', fn(Builder $query) => $query
                ->whereNull('deactivated_at')
                ->whereNotNull('password'))
            ->when($status === 'deactivated', fn(Builder $query) => $query->whereNotNull('deactivated_at'))
            ->when(isset($filters['role']), fn(Builder $query) => $query->whereHas(
                'roles',
                fn(Builder $roles) => $roles->where('uuid', $filters['role'])
            ))
            ->orderBy('full_name')
            ->orderBy('id');

        return $list->paginate($query);
    }

    public function find(string $id): User
    {
        $member = $this->team()
            ->with(['roles', 'permissions'])
            ->where('uuid', $id)
            ->first();

        if ($member === null) {
            throw ApiException::notFound('STAFF_NOT_FOUND');
        }

        return $member;
    }

    /**
     * Opens a team account and emails its first-password link.
     *
     * @param  list<string>  $roles  staff role ids
     * @param  list<string>  $permissions
     */
    public function invite(User $actor, string $email, string $name, array $roles, array $permissions): User
    {
        $staffRoles = $this->roles($roles);

        $this->access->assertHolds($actor, $this->permissionsIn($staffRoles), 'roles');
        $this->access->assertHolds($actor, $permissions);

        $member = DB::transaction(function () use ($actor, $email, $name, $staffRoles, $permissions) {
            if (User::query()->whereRaw('lower(email) = ?', [mb_strtolower($email)])->exists()) {
                throw ApiException::conflict('EMAIL_TAKEN', 'email');
            }

            $member = User::create([
                'full_name' => $name,
                'email' => $email,
            ]);
            $member->assignRole(Role::Admin->value);
            $member->assignRole($staffRoles);
            $member->givePermissionTo($permissions);

            $this->audit->record($actor, 'staff.invited', $member, [
                'roles' => $staffRoles->pluck('label')->all(),
                'permissions' => $permissions,
            ]);

            return $member;
        });

        $this->accounts->welcome($member);

        return $this->find($member->uuid);
    }

    /**
     * Replaces the member's staff roles and direct permissions.
     *
     * @param  list<string>  $roles  staff role ids
     * @param  list<string>  $permissions
     */
    public function updateAccess(User $actor, string $id, array $roles, array $permissions): User
    {
        $member = $this->find($id);
        $staffRoles = $this->roles($roles);

        $beforeRoles = $member->roles->where('is_staff', true);
        $addedRoles = $staffRoles->whereNotIn('id', $beforeRoles->pluck('id'));
        $removedRoles = $beforeRoles->whereNotIn('id', $staffRoles->pluck('id'));
        $beforePermissions = $member->permissions->pluck('name')->all();
        $addedPermissions = array_values(array_diff($permissions, $beforePermissions));
        $removedPermissions = array_values(array_diff($beforePermissions, $permissions));

        $this->access->assertHolds($actor, $this->permissionsIn($addedRoles->merge($removedRoles)), 'roles');
        $this->access->assertHolds($actor, [...$addedPermissions, ...$removedPermissions]);

        DB::transaction(function () use ($actor, $member, $staffRoles, $permissions, $addedRoles, $removedRoles, $addedPermissions, $removedPermissions) {
            $losesSuperAdmin = $removedRoles->contains(fn($role) => $role->name === StaffRole::superAdminName());

            if ($losesSuperAdmin) {
                $this->assertAnotherSuperAdmin($member);
            }

            $member->syncRoles([Role::Admin->value, ...$staffRoles->all()]);
            $member->syncPermissions($permissions);

            $this->audit->record($actor, 'staff.access_changed', $member, [
                'roles_added' => $addedRoles->pluck('label')->values()->all(),
                'roles_removed' => $removedRoles->pluck('label')->values()->all(),
                'permissions_added' => $addedPermissions,
                'permissions_removed' => $removedPermissions,
            ]);
        });

        return $this->find($id);
    }

    public function deactivate(User $actor, string $id): User
    {
        $member = $this->find($id);

        if ($member->deactivated_at !== null) {
            return $member;
        }

        if ($this->access->isSuperAdmin($member) && ! $this->access->isSuperAdmin($actor)) {
            throw ApiException::unprocessable('PERMISSION_NOT_HELD');
        }

        DB::transaction(function () use ($actor, $member) {
            if ($this->access->isSuperAdmin($member)) {
                $this->assertAnotherSuperAdmin($member);
            }

            $member->forceFill(['deactivated_at' => now()])->save();

            $this->audit->record($actor, 'staff.deactivated', $member);
        });

        $this->sessions->revokeAll($member);

        return $this->find($id);
    }

    public function reactivate(User $actor, string $id): User
    {
        $member = $this->find($id);

        if ($member->deactivated_at === null) {
            return $member;
        }

        DB::transaction(function () use ($actor, $member) {
            $member->forceFill(['deactivated_at' => null])->save();

            $this->audit->record($actor, 'staff.reactivated', $member);
        });

        return $this->find($id);
    }

    /**
     * Opens a super admin from the command line, where no actor exists yet.
     */
    public function openSuperAdmin(string $email, string $name): string
    {
        $this->catalogue->sync();

        $member = User::create([
            'full_name' => $name,
            'email' => $email,
        ]);
        $member->assignRole(Role::Admin->value);
        $member->assignRole($this->catalogue->superAdmin());

        return $this->accounts->welcome($member);
    }

    /**
     * @return Builder<User>
     */
    private function team(): Builder
    {
        return User::query()->role(Role::Admin->value);
    }

    /**
     * @param  list<string>  $ids
     * @return Collection<int, StaffRole>
     */
    private function roles(array $ids): Collection
    {
        $roles = StaffRole::query()
            ->with('permissions:id,name')
            ->whereIn('uuid', $ids)
            ->get();

        if ($roles->count() !== count(array_unique($ids))) {
            throw ApiException::notFound('ROLE_NOT_FOUND', 'roles');
        }

        return $roles;
    }

    /**
     * The catalogue permissions the roles carry, leaving out the kit's platform ones.
     *
     * @param  iterable<\Spatie\Permission\Models\Role>  $roles
     * @return list<string>
     */
    private function permissionsIn(iterable $roles): array
    {
        $names = [];

        foreach ($roles as $role) {
            $role->loadMissing('permissions:id,name');

            foreach ($role->permissions as $permission) {
                $names[$permission->name] = true;
            }
        }

        return array_values(array_intersect(AdminPermission::values(), array_keys($names)));
    }

    /**
     * Serialises super admin changes on the role row, then refuses to leave none active.
     */
    private function assertAnotherSuperAdmin(User $member): void
    {
        StaffRole::query()
            ->where('key', StaffRole::SUPER_ADMIN)
            ->lockForUpdate()
            ->first();

        if ($this->access->otherSuperAdmins($member) === 0) {
            throw ApiException::unprocessable('LAST_SUPER_ADMIN');
        }
    }
}
