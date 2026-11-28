<?php

namespace App\Services\Identity\Export;

use App\Enums\AccountKind;
use App\Models\ConsentRecord;
use App\Models\SignIn;
use App\Models\User;

/**
 * The account itself, the consents it gave and where it signed in.
 */
class IdentityPersonalData implements PersonalData
{
    public function export(User $user, PersonalDataWriter $writer): void
    {
        $writer->record('account', 'Your account', [
            'id' => $user->uuid,
            'full_name' => $user->full_name,
            'phone_number' => $user->phone,
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            'kinds' => AccountKind::values($user->kinds()),
            'two_factor_enabled' => $user->hasTwoFactor(),
            'created_at' => $user->created_at?->toIso8601ZuluString('millisecond'),
        ]);

        $writer->section('consents', 'Consents you gave', ConsentRecord::where('user_id', $user->id));
        $writer->section('sign_ins', 'Where you signed in', SignIn::where('user_id', $user->id), ['family', 'device_key', 'install_id']);
    }
}
