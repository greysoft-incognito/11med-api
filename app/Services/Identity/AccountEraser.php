<?php

namespace App\Services\Identity;

use App\Events\Identity\AccountErasing;
use App\Exceptions\ApiException;
use App\Models\AccountErasure;
use App\Models\LoginLockout;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Erasure under NDPR (app handover §3.1): the person's rows are removed for real, not soft
 * deleted; what the law keeps survives without them; the phone number is free again.
 */
class AccountEraser
{
    public function erase(User $user, string $password, ?string $reason): void
    {
        if ($user->password === null || ! Hash::check($password, $user->password)) {
            throw ApiException::unprocessable('PASSWORD_INCORRECT', 'password');
        }

        DB::transaction(function () use ($user, $reason) {
            AccountErasure::create([
                'role' => $user->role()?->value,
                'reason' => $reason,
                'erased_at' => now(),
            ]);

            AccountErasing::dispatch($user);

            $user->tokens()->delete();
            $user->notifications()->delete();
            $user->syncRoles([]);

            OtpCode::query()
                ->whereIn('target', ['phone:' . $user->phone, 'email:' . $user->id])
                ->delete();

            LoginLockout::where('phone', $user->phone)->delete();

            $user->forceDelete();
        });
    }
}
