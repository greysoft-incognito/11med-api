<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use App\Models\User;
use App\Services\Identity\Export\PersonalData;
use App\Services\Identity\Export\PersonalDataWriter;

class SubscriptionPersonalData implements PersonalData
{
    public function export(User $user, PersonalDataWriter $writer): void
    {
        $writer->section('subscription', 'Premium', Subscription::where('user_id', $user->id));
    }
}
