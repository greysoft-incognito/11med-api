<?php

namespace App\Services\Doctors;

use App\Models\DoctorDocument;
use App\Models\DoctorProfile;
use App\Models\User;
use App\Services\Identity\Export\PersonalData;
use App\Services\Identity\Export\PersonalDataWriter;

/**
 * A doctor's record with the team and the documents they sent. The payout account is shown by its last four only.
 */
class DoctorPersonalData implements PersonalData
{
    public function export(User $user, PersonalDataWriter $writer): void
    {
        $profiles = DoctorProfile::where('user_id', $user->id);

        $writer->section('doctor_profile', 'Your doctor record', $profiles->clone(), ['bank_account_number']);
        $writer->section('doctor_documents', 'Documents you sent', DoctorDocument::whereIn('doctor_profile_id', $profiles->clone()->select('id')));
    }
}
