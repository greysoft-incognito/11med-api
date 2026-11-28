<?php

namespace App\Services\Medications;

use App\Models\DoseLog;
use App\Models\Medication;
use App\Models\User;
use App\Services\Identity\Export\PersonalData;
use App\Services\Identity\Export\PersonalDataWriter;

/**
 * Medication schedules and every dose answered or missed.
 */
class MedicationPersonalData implements PersonalData
{
    public function export(User $user, PersonalDataWriter $writer): void
    {
        $writer->section('medications', 'Medications', Medication::where('user_id', $user->id));
        $writer->section('doses', 'Doses', DoseLog::where('user_id', $user->id));
    }
}
