<?php

namespace App\Services\Profile;

use App\Models\Caregiver;
use App\Models\MedicalProfile;
use App\Models\User;
use App\Services\Identity\Export\PersonalData;
use App\Services\Identity\Export\PersonalDataWriter;

/**
 * The medical profile, and the caregivers on both sides of the account.
 */
class ProfilePersonalData implements PersonalData
{
    public function export(User $user, PersonalDataWriter $writer): void
    {
        $writer->section('medical_profile', 'Medical profile', MedicalProfile::where('user_id', $user->id));
        $writer->section('caregivers', 'Your caregivers', Caregiver::where('patient_id', $user->id), ['invite_code']);
        $writer->section('caring_for', 'People you care for', Caregiver::where('caregiver_user_id', $user->id), ['invite_code']);
    }
}
