<?php

namespace App\Services\Guide;

use App\Models\GuideConversation;
use App\Models\GuideMessage;
use App\Models\User;
use App\Services\Identity\Export\PersonalData;
use App\Services\Identity\Export\PersonalDataWriter;

class GuidePersonalData implements PersonalData
{
    public function export(User $user, PersonalDataWriter $writer): void
    {
        $conversations = GuideConversation::where('user_id', $user->id)->select('id');

        $writer->section('guide_messages', 'Your conversation with the guide', GuideMessage::whereIn('conversation_id', $conversations));
    }
}
