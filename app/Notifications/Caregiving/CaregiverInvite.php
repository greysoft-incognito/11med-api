<?php

namespace App\Notifications\Caregiving;

use App\Notifications\Identity\SmsNotification;

/**
 * The invitation text: a store link and the code, as two things, so it survives an install.
 */
class CaregiverInvite extends SmsNotification
{
    public function __construct(
        public readonly ?string $patientName,
        public readonly string $code,
    ) {
        $this->afterCommit();
    }

    public function smsText(): string
    {
        return __('caregiving.sms.invite', [
            'name' => $this->patientName ?? __('caregiving.sms.someone'),
            'link' => config('elevenmed.app_link'),
            'code' => $this->code,
        ]);
    }
}
