<?php

namespace App\Notifications\Accounts;

use App\Notifications\Identity\SmsNotification;

/**
 * Tells an app account by SMS that the team suspended or reinstated it, since a suspended account
 * has no session or device left to reach.
 */
class AccountStatusChanged extends SmsNotification
{
    public function __construct(
        public readonly bool $suspended,
    ) {
        $this->afterCommit();
    }

    public function smsText(): string
    {
        return __($this->suspended ? 'accounts.sms.suspended' : 'accounts.sms.reinstated');
    }
}
