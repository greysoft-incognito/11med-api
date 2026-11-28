<?php

namespace App\Services\Laboratories;

use App\Models\LabBooking;
use App\Models\LabRequest;
use App\Models\User;
use App\Services\Identity\Export\PersonalData;
use App\Services\Identity\Export\PersonalDataWriter;

class LaboratoryPersonalData implements PersonalData
{
    public function export(User $user, PersonalDataWriter $writer): void
    {
        $writer->section('lab_requests', 'Test requests from doctors', LabRequest::where('patient_id', $user->id));
        $writer->section('lab_bookings', 'Laboratory bookings', LabBooking::where('patient_id', $user->id));
    }
}
