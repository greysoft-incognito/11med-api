<?php

namespace App\Http\Resources\Accounts;

use App\Http\Resources\ApiResource;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * `zAdminUser`: an app account as the team sees it, without any medical record. The detail view
 * adds `counts`.
 *
 * @property User $resource
 */
class AdminUserResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'id' => $user->uuid,
            'role' => $user->role()?->value,
            'full_name' => $user->full_name,
            'phone' => $user->phone,
            'email' => $user->email,
            'phone_verified' => $user->phone_verified_at !== null,
            'email_verified' => $user->email_verified_at !== null,
            'is_premium' => $user->isPremium(),
            'doctor_status' => $user->doctorProfile?->verification_status?->value,
            'suspended_at' => $this->iso($user->suspended_at),
            'suspension_reason' => $user->suspension_reason,
            'created_at' => $this->iso($user->created_at),
            'last_active_at' => $this->iso($user->last_active_at),
            'counts' => $this->when($user->hasAttribute('orders_count'), fn() => [
                'orders' => (int) $user->orders_count,
                'lab_bookings' => (int) $user->lab_bookings_count,
                'consultations' => (int) $user->consultations_count,
            ]),
        ];
    }
}
