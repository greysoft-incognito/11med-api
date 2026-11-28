<?php

namespace App\Services\Subscriptions;

use App\Enums\AccountKind;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\Audit\AuditLog;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Premium, recorded not sold (app spec §7.3): the team records a payment taken outside the app,
 * and the app reads whether the account is premium. The only writer of the subscription tables.
 */
class Subscriptions
{
    public function __construct(
        private readonly AuditLog $audit,
    ) {}

    /**
     * The caller's own subscription; anyone else's is 404.
     */
    public function stateFor(User $caller, string $userId): SubscriptionState
    {
        if ($caller->uuid !== $userId) {
            throw ApiException::notFound();
        }

        return SubscriptionState::of($caller->subscription);
    }

    /**
     * The recorded subscriptions, most recently changed first; `q` matches name, phone or reference.
     */
    public function records(?string $q, ListQuery $list): Page
    {
        $query = Subscription::query()
            ->with('user:id,uuid,full_name,firstname,lastname,phone')
            ->when($q !== null && $q !== '', fn(Builder $query) => $this->search($query, $q))
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        return $list->paginate($query);
    }

    /**
     * Records premium through `$premiumUntil` for the patient with this phone, against a payment
     * reference that has never been recorded.
     */
    public function record(User $admin, string $phone, string $premiumUntil, string $reference): Subscription
    {
        $patient = User::query()
            ->ofKind(AccountKind::Patient)
            ->where('phone', PhoneNumber::normalise($phone))
            ->whereNotNull('phone_verified_at')
            ->first();

        if ($patient === null) {
            throw ApiException::notFound('PATIENT_NOT_FOUND', 'phone');
        }

        try {
            $subscription = DB::transaction(function () use ($admin, $patient, $premiumUntil, $reference) {
                $patient->newQuery()->lockForUpdate()->find($patient->id);

                if (SubscriptionPayment::withTrashed()->where('reference', $reference)->exists()) {
                    throw ApiException::conflict('REFERENCE_USED', 'reference');
                }

                SubscriptionPayment::create([
                    'user_id' => $patient->id,
                    'reference' => $reference,
                    'premium_until' => $premiumUntil,
                    'recorded_by' => $admin->id,
                    'recorded_at' => now(),
                ]);

                $subscription = Subscription::query()->firstOrNew(['user_id' => $patient->id]);
                $subscription->fill([
                    'premium_until' => $premiumUntil,
                    'reference' => $reference,
                    'recorded_by' => $admin->id,
                ]);
                $subscription->save();

                $this->audit->record($admin, 'subscription.recorded', $subscription, [
                    'patient_id' => $patient->uuid,
                    'premium_until' => $premiumUntil,
                    'reference' => $reference,
                ]);

                return $subscription;
            });
        } catch (UniqueConstraintViolationException) {
            throw ApiException::conflict('REFERENCE_USED', 'reference');
        }

        return $subscription->setRelation('user', $patient);
    }

    /**
     * Patients whose premium holds today.
     */
    public function activeCount(): int
    {
        return Subscription::query()
            ->active()
            ->count();
    }

    /**
     * @param  Builder<Subscription>  $query
     */
    private function search(Builder $query, string $q): void
    {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $phone = '%' . addcslashes(PhoneNumber::normalise($q), '%_\\') . '%';

        $query->where(
            fn(Builder $query) => $query
                ->where('reference', 'ilike', $like)
                ->orWhereHas(
                    'user',
                    fn(Builder $user) => $user
                        ->where('full_name', 'ilike', $like)
                        ->orWhere('phone', 'ilike', $phone)
                )
        );
    }
}
