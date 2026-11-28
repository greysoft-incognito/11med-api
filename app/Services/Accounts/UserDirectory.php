<?php

namespace App\Services\Accounts;

use App\Enums\Role;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\Consultation;
use App\Models\LabBooking;
use App\Models\Order;
use App\Models\RefreshToken;
use App\Models\User;
use App\Support\LikePattern;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;

/**
 * The team's view of patient and doctor accounts: who they are and how they stand, never their
 * medical records.
 */
class UserDirectory
{
    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    /**
     * Newest first; `q` over name, phone and email.
     */
    public function list(?Role $role, ?string $status, ?string $search, ListQuery $list): Page
    {
        $search = trim((string) $search);
        $pattern = LikePattern::contains($search);
        $phone = preg_match(PhoneNumber::PATTERN, $search) === 1 ? LikePattern::contains(PhoneNumber::normalise($search)) : $pattern;

        $query = $this
            ->accounts()
            ->when($role !== null, fn(Builder $q) => $q->role($role->value))
            ->when($status === self::ACTIVE, fn(Builder $q) => $q->whereNull('suspended_at'))
            ->when($status === self::SUSPENDED, fn(Builder $q) => $q->whereNotNull('suspended_at'))
            ->when($search !== '', fn(Builder $q) => $q->where(fn(Builder $q) => $q
                ->whereLike('full_name', $pattern)
                ->orWhereLike('phone', $phone)
                ->orWhereLike('email', $pattern)))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return $list->paginate($query);
    }

    /**
     * One account with its activity counts.
     */
    public function find(string $id): User
    {
        $user = $this
            ->accounts()
            ->where('uuid', $id)
            ->addSelect([
                'orders_count' => Order::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('patient_id', 'users.id'),
                'lab_bookings_count' => LabBooking::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('patient_id', 'users.id'),
                'consultations_count' => Consultation::query()
                    ->selectRaw('count(*)')
                    ->where(fn($q) => $q
                        ->whereColumn('patient_id', 'users.id')
                        ->orWhereColumn('doctor_id', 'users.id')),
            ])
            ->first();

        return $user ?? throw ApiException::notFound(User::NOT_FOUND);
    }

    /**
     * A patient or doctor account by public id, locked for a change.
     */
    public function locate(string $id): User
    {
        $user = User::query()
            ->role(Role::values(Role::app()))
            ->withoutRole(Role::values(Role::portal()))
            ->where('uuid', $id)
            ->lockForUpdate()
            ->first();

        return $user ?? throw ApiException::notFound(User::NOT_FOUND);
    }

    /**
     * App accounts with what the list shows: role, premium record, doctor status and the last
     * time a session was refreshed.
     */
    private function accounts(): Builder
    {
        return User::query()
            ->role(Role::values(Role::app()))
            ->withoutRole(Role::values(Role::portal()))
            ->select('users.*')
            ->addSelect([
                'last_active_at' => RefreshToken::query()
                    ->select('created_at')
                    ->whereColumn('user_id', 'users.id')
                    ->orderByDesc('created_at')
                    ->limit(1),
            ])
            ->with(['roles', 'subscription', 'doctorProfile:id,user_id,verification_status']);
    }
}
