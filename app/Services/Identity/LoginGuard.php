<?php

namespace App\Services\Identity;

use App\Exceptions\ApiException;
use App\Models\LoginLockout;

/**
 * Failed sign-ins per phone number, whether or not it has an account: five lock it for
 * fifteen minutes, ten lock it until the password is reset (app spec §2.1.4).
 */
class LoginGuard
{
    public function check(string $phone): void
    {
        $row = LoginLockout::firstWhere('phone', $phone);

        if ($row?->hard_locked_at !== null) {
            throw ApiException::locked('AUTH_ACCOUNT_LOCKED', ['requires_email_unlock' => true]);
        }

        if ($row?->locked_until?->isFuture()) {
            throw ApiException::locked('AUTH_ACCOUNT_LOCKED', [
                'locked_for_ms' => (int) now()->diffInMilliseconds($row->locked_until),
            ]);
        }
    }

    /**
     * Count a failure and refuse with what is left.
     */
    public function fail(string $phone): never
    {
        $row = LoginLockout::firstOrNew(['phone' => $phone]);
        $row->failures++;

        if ($row->failures >= config('elevenmed.login.hard_lock_after')) {
            $row->hard_locked_at = now();
            $row->save();

            throw ApiException::locked('AUTH_ACCOUNT_LOCKED', ['requires_email_unlock' => true]);
        }

        if ($row->failures >= config('elevenmed.login.lock_after')) {
            $row->locked_until = now()->addMinutes(config('elevenmed.login.lock_minutes'));
        }

        $row->save();

        throw ApiException::unauthorized('AUTH_INVALID_CREDENTIALS', [
            'attempts_remaining' => max(0, config('elevenmed.login.lock_after') - $row->failures),
        ]);
    }

    public function clear(string $phone): void
    {
        LoginLockout::where('phone', $phone)->delete();
    }
}
