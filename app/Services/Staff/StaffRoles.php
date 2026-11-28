<?php

namespace App\Services\Staff;

use App\Exceptions\ApiException;
use App\Models\StaffRole;
use App\Models\User;
use App\Services\Audit\AuditLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The super admin's roles: named sets of admin permissions. The super admin role itself is fixed.
 */
class StaffRoles
{
    public function __construct(
        private readonly StaffAccess $access,
        private readonly AuditLog $audit,
    ) {}

    /**
     * Every staff role, a short list read whole, the super admin first, with its permissions and
     * how many hold it.
     *
     * @return Collection<int, StaffRole>
     */
    public function all(): Collection
    {
        return StaffRole::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderByRaw('CASE WHEN key = ? THEN 0 ELSE 1 END', [StaffRole::SUPER_ADMIN])
            ->orderBy('label')
            ->get();
    }

    public function find(string $id): StaffRole
    {
        $role = StaffRole::query()
            ->where('uuid', $id)
            ->first();

        if ($role === null) {
            throw ApiException::notFound('ROLE_NOT_FOUND');
        }

        return $role;
    }

    /**
     * @param  array{name: string, description?: ?string, permissions: list<string>}  $input
     */
    public function create(User $actor, array $input): StaffRole
    {
        $this->access->assertHolds($actor, $input['permissions']);

        $role = DB::transaction(function () use ($actor, $input) {
            $this->assertNameFree($input['name']);

            $role = StaffRole::open([
                'label' => $input['name'],
                'description' => $input['description'] ?? null,
            ]);
            $role->syncPermissions($input['permissions']);

            $this->audit->record($actor, 'role.created', $role, [
                'name' => $role->label,
                'permissions' => $input['permissions'],
            ]);

            return $role;
        });

        return $this->reload($role);
    }

    /**
     * @param  array{name: string, description?: ?string, permissions: list<string>}  $input
     */
    public function update(User $actor, string $id, array $input): StaffRole
    {
        $role = $this->editable($id);
        $before = $role
            ->permissions()
            ->pluck('name')
            ->all();
        $added = array_values(array_diff($input['permissions'], $before));
        $removed = array_values(array_diff($before, $input['permissions']));

        $this->access->assertHolds($actor, [...$added, ...$removed]);

        DB::transaction(function () use ($actor, $role, $input, $added, $removed) {
            $this->assertNameFree($input['name'], $role);

            $role->fill([
                'label' => $input['name'],
                'description' => $input['description'] ?? null,
            ])->save();
            $role->syncPermissions($input['permissions']);

            $this->audit->record($actor, 'role.updated', $role, [
                'name' => $role->label,
                'added' => $added,
                'removed' => $removed,
            ]);
        });

        return $this->reload($role);
    }

    /**
     * Deletes a role. Its holders move to `$reassignTo` first; without one, a held role stays.
     */
    public function delete(User $actor, string $id, ?string $reassignTo): void
    {
        $role = $this->editable($id);
        $target = $reassignTo === null ? null : $this->findTarget($reassignTo, $role);

        if ($target !== null) {
            $this->access->assertHolds($actor, $target->permissions()->pluck('name'), 'reassign_to');
        }

        DB::transaction(function () use ($actor, $role, $target) {
            $holders = $role->users()->count();

            if ($holders > 0 && $target === null) {
                throw ApiException::unprocessable('ROLE_IN_USE', 'reassign_to');
            }

            if ($target !== null) {
                $this->moveHolders($role, $target);
            }

            $this->audit->record($actor, 'role.deleted', $role, [
                'name' => $role->label,
                'moved_to' => $target?->uuid,
                'holders' => $holders,
            ]);

            $role->delete();
        });
    }

    private function editable(string $id): StaffRole
    {
        $role = $this->find($id);

        if ($role->isSuperAdmin()) {
            throw ApiException::unprocessable('ROLE_PROTECTED');
        }

        return $role;
    }

    private function findTarget(string $id, StaffRole $role): StaffRole
    {
        $target = StaffRole::query()
            ->where('uuid', $id)
            ->whereKeyNot($role->id)
            ->first();

        if ($target === null) {
            throw ApiException::notFound('ROLE_NOT_FOUND', 'reassign_to');
        }

        return $target;
    }

    /**
     * Gives the target role to every holder in one statement; the old pivot rows go with the role.
     */
    private function moveHolders(StaffRole $from, StaffRole $to): void
    {
        DB::statement(
            'INSERT INTO model_has_roles (role_id, model_type, model_id)
             SELECT ?, model_type, model_id FROM model_has_roles WHERE role_id = ?
             ON CONFLICT DO NOTHING',
            [$to->id, $from->id],
        );
    }

    private function assertNameFree(string $name, ?StaffRole $except = null): void
    {
        $taken = StaffRole::query()
            ->whereRaw('lower(label) = ?', [mb_strtolower(trim($name))])
            ->when($except !== null, fn($q) => $q->whereKeyNot($except->id))
            ->exists();

        if ($taken) {
            throw ApiException::conflict('ROLE_NAME_TAKEN', 'name');
        }
    }

    private function reload(StaffRole $role): StaffRole
    {
        return StaffRole::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->findOrFail($role->id);
    }
}
