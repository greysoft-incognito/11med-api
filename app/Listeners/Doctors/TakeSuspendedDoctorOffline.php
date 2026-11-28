<?php

namespace App\Listeners\Doctors;

use App\Events\Accounts\AccountSuspended;
use App\Models\DoctorProfile;

/**
 * A suspended doctor is no longer available now, so reinstatement does not list them as waiting.
 */
class TakeSuspendedDoctorOffline
{
    public function handle(AccountSuspended $event): void
    {
        DoctorProfile::query()
            ->where('user_id', $event->user->id)
            ->update(['is_available_now' => false]);
    }
}
