<?php

namespace Database\Seeders\Demo;

use App\Enums\AccountKind;
use App\Enums\ConsentContext;
use App\Models\ConsentRecord;
use App\Models\User;
use App\Services\Identity\PolicyConsent;

/**
 * App accounts as a completed sign-up leaves them: phone verified, consent recorded, signed in
 * with the mock's demo password. No code is texted.
 */
class DemoAccounts
{
    public const PASSWORD = 'Password1';

    public static function create(string $fullName, string $phone, ?string $email, AccountKind $kind): User
    {
        $user = User::create([
            'full_name' => $fullName,
            'phone' => $phone,
            'email' => $email,
            'password' => self::PASSWORD,
        ]);

        $user->forceFill(['phone_verified_at' => now()->subDays(60), 'email_verified_at' => $email === null ? null : now()->subDays(60)])->save();
        $user->grantKind($kind);

        ConsentRecord::create([
            'user_id' => $user->id,
            'context' => ConsentContext::Register,
            'policy_version' => app(PolicyConsent::class)->recordable($kind),
            'accepted_at' => now()->subDays(60),
            'recorded_at' => now()->subDays(60),
        ]);

        return $user;
    }
}
