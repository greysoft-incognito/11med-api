<?php

namespace App\Services\Staff;

use App\Enums\AdminPermission as P;
use App\Models\StaffRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The permission catalogue and the default staff roles, kept in step with `AdminPermission`.
 * Safe to run on every deploy: defaults are created once; the super admin always holds every admin
 * permission and the kit's platform permissions.
 */
class StaffCatalogue
{
    /**
     * @var array<string, array{label: string, description: string, permissions: list<P>}>
     */
    private const DEFAULTS = [
        StaffRole::SUPER_ADMIN => [
            'label' => 'Super admin',
            'description' => 'Every permission, including staff and roles.',
            'permissions' => [],
        ],
        'verification' => [
            'label' => 'Verification',
            'description' => 'Reviews doctors and partner applications.',
            'permissions' => [P::SummaryView, P::DoctorsView, P::DoctorsReview, P::PartnersView, P::PartnersReview],
        ],
        'finance' => [
            'label' => 'Finance',
            'description' => 'Payments, payouts and subscriptions.',
            'permissions' => [P::SummaryView, P::PaymentsView, P::PayoutsView, P::PayoutsSettle, P::SubscriptionsView, P::SubscriptionsRecord],
        ],
        'support' => [
            'label' => 'Support',
            'description' => 'Reads users, orders, bookings and consultations, cancels and refunds them, answers support.',
            'permissions' => [
                P::SummaryView,
                P::UsersView,
                P::OrdersView,
                P::OrdersCancel,
                P::BookingsView,
                P::BookingsCancel,
                P::ConsultationsView,
                P::ConsultationsCancel,
                P::SupportView,
                P::SupportReply,
            ],
        ],
        'catalogue' => [
            'label' => 'Catalogue',
            'description' => 'The laboratory test catalogue.',
            'permissions' => [P::SummaryView, P::CatalogueView, P::CatalogueEdit],
        ],
    ];

    public function sync(): void
    {
        DB::transaction(function () {
            $superAdminPermissions = [...P::values(), ...config('permission-defs.permissions', [])];

            foreach ($superAdminPermissions as $name) {
                Permission::findOrCreate($name, StaffRole::GUARD);
            }

            $firstRun = ! StaffRole::query()
                ->where('key', StaffRole::SUPER_ADMIN)
                ->exists();

            if ($firstRun) {
                $this->createDefaults();
            }

            $superAdmin = $this->superAdmin();
            $superAdmin->syncPermissions($superAdminPermissions);

            if ($firstRun) {
                $this->promoteExistingAdmins($superAdmin);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function superAdmin(): StaffRole
    {
        return StaffRole::query()
            ->where('key', StaffRole::SUPER_ADMIN)
            ->firstOrFail();
    }

    private function createDefaults(): void
    {
        foreach (self::DEFAULTS as $key => $default) {
            $role = StaffRole::open([
                'key' => $key,
                'label' => $default['label'],
                'description' => $default['description'],
            ]);

            $role->syncPermissions(P::values($default['permissions']));
        }
    }

    /**
     * Team accounts opened before staff roles existed held every power; they keep it.
     */
    private function promoteExistingAdmins(StaffRole $superAdmin): void
    {
        User::query()
            ->whereNotNull('staff_since')
            ->chunkById(200, function ($admins) use ($superAdmin) {
                foreach ($admins as $admin) {
                    $admin->assignRole($superAdmin);
                }
            });
    }
}
