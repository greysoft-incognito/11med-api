<?php

namespace App\Notifications\Identity;

class PhoneCode extends SmsNotification
{
    public function __construct(
        public readonly string $code,
        public readonly int $minutes,
    ) {
        $this->afterCommit();
    }

    public function smsText(): string
    {
        return __('identity.sms.code', ['code' => $this->code, 'minutes' => $this->minutes]);
    }
}
