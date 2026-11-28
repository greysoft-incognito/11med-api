<?php

namespace App\Http\Controllers\Staff;

use App\Enums\AdminPermission;
use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\DeleteStaffRoleRequest;
use App\Http\Requests\Staff\StaffRoleRequest;
use App\Http\Resources\EmptyResource;
use App\Http\Resources\Staff\AdminPermissionCollection;
use App\Http\Resources\Staff\StaffRoleCollection;
use App\Http\Resources\Staff\StaffRoleResource;
use App\Services\Staff\StaffRoles;
use Illuminate\Http\JsonResponse;

class StaffRoleController extends Controller
{
    public function __construct(
        private readonly StaffRoles $roles,
    ) {}

    public function permissions(): AdminPermissionCollection
    {
        $permissions = AdminPermission::cases();

        return new AdminPermissionCollection($permissions);
    }

    public function index(): StaffRoleCollection
    {
        $roles = $this->roles->all();

        return new StaffRoleCollection($roles);
    }

    public function store(StaffRoleRequest $request): JsonResponse
    {
        $role = $this->roles->create($request->user(), $request->validated());

        return (new StaffRoleResource($role))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }

    public function update(StaffRoleRequest $request, string $id): StaffRoleResource
    {
        $role = $this->roles->update($request->user(), $id, $request->validated());

        return new StaffRoleResource($role);
    }

    public function destroy(DeleteStaffRoleRequest $request, string $id): EmptyResource
    {
        $this->roles->delete($request->user(), $id, $request->validated('reassign_to'));

        return new EmptyResource;
    }
}
