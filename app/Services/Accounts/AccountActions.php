<?php

namespace App\Services\Accounts;

use App\Events\Accounts\AccountSuspended;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Notifications\Accounts\AccountStatusChanged;
use App\Services\Audit\AuditLog;
use App\Services\Identity\AppAccounts;
use App\Services\Identity\CodeSent;
use App\Services\Identity\EmailAddresses;
use App\Services\Identity\Passwords;
use App\Services\Identity\Sessions;
use App\Support\Sms;
use Illuminate\Support\Facades\DB;

/**
 * What the team may do to a patient or doctor account: suspend and reinstate it, end its sessions,
 * and send the codes the person would otherwise ask for themselves. Each is audited.
 */
class AccountActions
{
    public function __construct(
        private readonly UserDirectory $directory,
        private readonly Sessions $sessions,
        private readonly AuditLog $audit,
        private readonly AppAccounts $accounts,
        private readonly EmailAddresses $emails,
        private readonly Passwords $passwords,
    ) {}

    /**
     * No session opens while suspended; every current one ends now.
     */
    public function suspend(User $actor, string $id, string $reason): User
    {
        $user = DB::transaction(function () use ($actor, $id, $reason) {
            $user = $this->directory->locate($id);

            if ($user->isSuspended()) {
                throw ApiException::unprocessable('ALREADY_SUSPENDED');
            }

            $user->forceFill([
                'suspended_at' => now(),
                'suspension_reason' => $reason,
            ])->save();

            $this->sessions->revokeAll($user);
            $this->audit->record($actor, 'user.suspended', $user, ['reason' => $reason]);

            AccountSuspended::dispatch($user);

            return $user;
        });

        Sms::to($user->phone)->notify(new AccountStatusChanged(suspended: true));

        return $this->directory->find($id);
    }

    public function reinstate(User $actor, string $id): User
    {
        $user = DB::transaction(function () use ($actor, $id) {
            $user = $this->directory->locate($id);

            if (! $user->isSuspended()) {
                throw ApiException::unprocessable('NOT_SUSPENDED');
            }

            $user->forceFill([
                'suspended_at' => null,
                'suspension_reason' => null,
            ])->save();

            $this->audit->record($actor, 'user.reinstated', $user);

            return $user;
        });

        Sms::to($user->phone)->notify(new AccountStatusChanged(suspended: false));

        return $this->directory->find($id);
    }

    /**
     * Ends every session; the person signs in again with their password.
     */
    public function signOut(User $actor, string $id): User
    {
        DB::transaction(function () use ($actor, $id) {
            $user = $this->directory->locate($id);

            $this->sessions->revokeAll($user);
            $this->audit->record($actor, 'user.signed_out', $user);
        });

        return $this->directory->find($id);
    }

    /**
     * Another phone or email confirmation code, within the person's own resend budget.
     */
    public function resendVerification(User $actor, string $id, string $channel): CodeSent
    {
        $user = $this->directory->locate($id);

        if ($channel === 'phone' && $user->phone_verified_at !== null) {
            throw ApiException::unprocessable('PHONE_VERIFIED');
        }

        $sent = $channel === 'phone'
            ? $this->accounts->resendCode($user->phone)
            : $this->emails->resend($user);

        $this->audit->record($actor, 'user.verification_sent', $user, ['channel' => $channel]);

        return $sent;
    }

    /**
     * A password reset code to the account's phone, as the person's own "forgot password" sends.
     */
    public function sendPasswordReset(User $actor, string $id): CodeSent
    {
        $user = $this->directory->locate($id);

        $sent = $this->passwords->forgot($user->phone);

        $this->audit->record($actor, 'user.password_reset_sent', $user);

        return $sent;
    }
}
