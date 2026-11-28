<?php

namespace App\Services\Readings;

use App\Models\Reading;
use App\Models\User;
use App\Services\Identity\Export\PersonalData;
use App\Services\Identity\Export\PersonalDataWriter;

class ReadingPersonalData implements PersonalData
{
    public function export(User $user, PersonalDataWriter $writer): void
    {
        $writer->section('readings', 'Readings', Reading::where('user_id', $user->id));
    }
}
