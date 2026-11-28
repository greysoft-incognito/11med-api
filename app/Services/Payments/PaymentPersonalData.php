<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\User;
use App\Services\Identity\Export\PersonalData;
use App\Services\Identity\Export\PersonalDataWriter;

class PaymentPersonalData implements PersonalData
{
    public function export(User $user, PersonalDataWriter $writer): void
    {
        $writer->section('payments', 'Payments', Payment::where('user_id', $user->id), ['authorization_url', 'access_code']);
    }
}
