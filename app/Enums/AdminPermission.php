<?php

namespace App\Enums;

/**
 * What a team account may do inside the admin portal, one case per area and action. A staff role
 * is a named set of these; an admin may also hold any of them directly.
 */
enum AdminPermission: string
{
    case SummaryView = 'summary.view';

    case DoctorsView = 'doctors.view';
    case DoctorsReview = 'doctors.review';

    case PartnersView = 'partners.view';
    case PartnersReview = 'partners.review';

    case PaymentsView = 'payments.view';
    case PayoutsView = 'payouts.view';
    case PayoutsSettle = 'payouts.settle';
    case SubscriptionsView = 'subscriptions.view';
    case SubscriptionsRecord = 'subscriptions.record';

    case CatalogueView = 'catalogue.view';
    case CatalogueEdit = 'catalogue.edit';

    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case OrdersView = 'orders.view';
    case OrdersCancel = 'orders.cancel';
    case BookingsView = 'bookings.view';
    case BookingsCancel = 'bookings.cancel';
    case ConsultationsView = 'consultations.view';
    case ConsultationsCancel = 'consultations.cancel';
    case SupportView = 'support.view';
    case SupportReply = 'support.reply';

    case SettingsView = 'settings.view';
    case SettingsEdit = 'settings.edit';
    case AnnouncementsView = 'announcements.view';
    case AnnouncementsSend = 'announcements.send';

    case StaffView = 'staff.view';
    case StaffManage = 'staff.manage';
    case RolesView = 'roles.view';
    case RolesManage = 'roles.manage';
    case AuditView = 'audit.view';

    /**
     * The area the permission belongs to, the part before the dot.
     */
    public function area(): string
    {
        return explode('.', $this->value)[0];
    }

    /**
     * The route middleware that admits holders of any of the given permissions.
     */
    public static function middleware(self ...$permissions): string
    {
        return 'permission:' . implode('|', array_map(fn(self $permission) => $permission->value, $permissions));
    }

    /**
     * @param  list<self>|null  $permissions
     * @return list<string>
     */
    public static function values(?array $permissions = null): array
    {
        return array_map(fn(self $permission) => $permission->value, $permissions ?? self::cases());
    }
}
