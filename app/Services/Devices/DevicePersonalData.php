<?php

namespace App\Services\Devices;

use App\Models\Device;
use App\Models\User;
use App\Services\Identity\Export\PersonalData;
use App\Services\Identity\Export\PersonalDataWriter;

class DevicePersonalData implements PersonalData
{
    public function export(User $user, PersonalDataWriter $writer): void
    {
        $writer->section('devices', 'Phones registered for notifications', Device::where('user_id', $user->id));
    }
}
