<?php

namespace App\Services\Notifications;

use App\Models\AppNotification;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Identity\Export\PersonalData;
use App\Services\Identity\Export\PersonalDataWriter;

class NotificationPersonalData implements PersonalData
{
    public function export(User $user, PersonalDataWriter $writer): void
    {
        $writer->section('notifications', 'Notifications', AppNotification::where('user_id', $user->id), ['dedupe_key']);
        $writer->section('notification_preferences', 'Notification settings', NotificationPreference::where('user_id', $user->id));
    }
}
