<?php

namespace App\Services\Caregiving;

use App\Enums\CaregiverStatus;
use App\Events\Caregiving\CaregiverAccepted;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\Caregiver;
use App\Models\User;
use App\Notifications\Caregiving\CaregiverInvite;
use App\Services\Subscriptions\Entitlements;
use App\Support\PhoneNumber;
use App\Support\Sms;
use Illuminate\Support\Facades\DB;

/**
 * Caregivers (app spec §6.5): a patient invites a number, the person behind it accepts with the
 * code, and from then reads the patient's schedule and nothing else until the patient revokes it.
 */
class Caregivers
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRTUVWXYZ23456789';

    private const CODE_LENGTH = 6;

    public function __construct(
        private readonly Entitlements $entitlements,
    ) {}

    public function invite(User $patient, string $phone): Caregiver
    {
        $phone = PhoneNumber::normalise($phone);

        $caregiver = DB::transaction(function () use ($patient, $phone) {
            $patient->newQuery()->lockForUpdate()->find($patient->id);

            $mine = Caregiver::query()->where('patient_id', $patient->id);

            // The more specific refusal first: re-inviting someone is not adding a second person.
            if ($mine->clone()->where('phone', $phone)->exists()) {
                throw ApiException::conflict('CAREGIVER_ALREADY_INVITED', 'phone');
            }

            $limit = $this->entitlements->caregiverLimit($patient);
            $current = $mine->count();

            if ($current >= $limit) {
                throw ApiException::unprocessable('CAREGIVER_LIMIT_REACHED', null, ['limit' => $limit, 'current' => $current]);
            }

            return Caregiver::create([
                'patient_id' => $patient->id,
                'phone' => $phone,
                'invite_code' => $this->freshCode(),
                'status' => CaregiverStatus::Pending,
                'invited_at' => now(),
            ]);
        });

        Sms::to($phone)->notify(new CaregiverInvite($patient->full_name, $caregiver->invite_code));

        return $caregiver;
    }

    public function accept(User $user, string $code): Caregiver
    {
        $caregiver = DB::transaction(function () use ($user, $code) {
            $caregiver = Caregiver::query()
                ->where('invite_code', strtoupper(trim($code)))
                ->lockForUpdate()
                ->first();

            if ($caregiver === null) {
                throw ApiException::notFound('INVITE_NOT_FOUND', 'code');
            }

            $alreadyCaring = Caregiver::query()
                ->where('patient_id', $caregiver->patient_id)
                ->where('caregiver_user_id', $user->id)
                ->exists();

            if ($caregiver->status === CaregiverStatus::Active || $alreadyCaring) {
                throw ApiException::conflict('INVITE_ALREADY_USED', 'code');
            }

            if ($caregiver->patient_id === $user->id) {
                throw ApiException::unprocessable('INVITE_SELF', 'code');
            }

            $caregiver->fill([
                'status' => CaregiverStatus::Active,
                'caregiver_user_id' => $user->id,
                'accepted_at' => now(),
            ]);
            $caregiver->save();

            return $caregiver;
        });

        CaregiverAccepted::dispatch($caregiver);

        return $caregiver->load('patient');
    }

    /**
     * Revocation is the patient's right and takes effect at once (spec §6.5).
     */
    public function revoke(User $patient, string $caregiverId): void
    {
        $caregiver = Caregiver::query()
            ->where('patient_id', $patient->id)
            ->where('uuid', $caregiverId)
            ->first();

        if ($caregiver === null) {
            throw ApiException::notFound(Caregiver::NOT_FOUND);
        }

        $caregiver->delete();
    }

    /**
     * The patient's caregivers, pending and active.
     */
    public function of(User $patient, ListQuery $list): Page
    {
        $query = Caregiver::query()
            ->with('caregiver:id,full_name')
            ->where('patient_id', $patient->id)
            ->orderBy('invited_at')
            ->orderBy('id');

        return $list->paginate($query);
    }

    /**
     * The patients whose schedules this account may read.
     */
    public function caringFor(User $user, ListQuery $list): Page
    {
        $query = Caregiver::query()
            ->with('patient:id,uuid,full_name')
            ->where('caregiver_user_id', $user->id)
            ->where('status', CaregiverStatus::Active)
            ->orderBy('accepted_at')
            ->orderBy('id');

        return $list->paginate($query);
    }

    /**
     * Whether `$user` may read `$patient`'s schedule (the Medications module asks).
     */
    public function canRead(User $user, User $patient): bool
    {
        return Caregiver::query()
            ->where('patient_id', $patient->id)
            ->where('caregiver_user_id', $user->id)
            ->where('status', CaregiverStatus::Active)
            ->exists();
    }

    private function freshCode(): string
    {
        do {
            $code = '';

            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (Caregiver::withTrashed()->where('invite_code', $code)->exists());

        return $code;
    }
}
