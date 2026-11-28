<?php

use App\Enums\Role;
use App\Models\User;

// The kit's platform permissions. Only the super admin holds them, through the gate.
$permissions = [
    'dashboard',
    'transactions',
    'manage-users',
    'manage-admins',
    'configuration',
    'manage-configuration',

    'users.user',
    'users.list',
    'users.create',
    'users.update',
    'users.delete',

    'notifications-temps',
];

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | When syncing roles and permissions using the app:sync-roles command, this
    | model will be considered as the user model and used to associte roles and
    | permissions to the indicated users.
    */
    'user-model' => User::class,
    /*
    |--------------------------------------------------------------------------
    | Super Admin Role
    |--------------------------------------------------------------------------
    |
    | This is the role that would be considered as the super admin role: the
    | staff role every permission check passes for (App\Models\StaffRole).
    */
    'super-admin-role' => 'staff:super-admin',
    /*
    |--------------------------------------------------------------------------
    | Role List
    |--------------------------------------------------------------------------
    |
    | These are roles that will be made available  to the user.
    | Feel free to add or remove as per your requirements.
    */
    'roles' => Role::values(),
    /*
    |--------------------------------------------------------------------------
    | Elevated Role List
    |--------------------------------------------------------------------------
    |
    | Users with any of the roles listed here are considered to have elevated access
    | Listed roles should already be defined in [roles] above.
    */
    'elevated-roles' => [
        'admin',
    ],
    /*
    |--------------------------------------------------------------------------
    | Permission List
    |--------------------------------------------------------------------------
    |
    | These are permissions will be attached to all roles unless they appear in
    | the exclusionlist.
    | Feel free to add or remove as per your requirements.
    */
    'permissions' => $permissions,
    /*
    |--------------------------------------------------------------------------
    | Exclusion List
    |--------------------------------------------------------------------------
    |
    | If there are permisions you do not want to attach to a particlular role
    | you can add them here using the role name as key.
    */
    'exclusions' => [
        'patient' => $permissions,
        'doctor' => $permissions,
        'pharmacy' => $permissions,
        'laboratory' => $permissions,
        'admin' => $permissions,
    ],
    /*
    |--------------------------------------------------------------------------
    | Admin Roles
    |--------------------------------------------------------------------------
    |
    | Users with any of the following roles are considered admins in general contexts.
    */
    'admin_roles' => [
        'admin',
    ],
];
