<?php

return [
    'sms' => [
        'code' => 'Your 11med code is :code. It expires in :minutes minutes. Never share it.',
    ],
    'email_code' => [
        'subject' => 'Confirm your email address',
        'line' => 'Enter this code in 11med to confirm this address: :code',
        'expiry' => 'It expires in :minutes minutes. If you did not ask for it, ignore this email.',
    ],
    'portal_link' => [
        'reset' => [
            'subject' => 'Reset your 11med password',
            'line' => 'Someone asked to reset the password for this address. If it was you, choose a new one.',
            'action' => 'Choose a new password',
            'expiry' => 'The link works once, for an hour. If you did not ask, ignore this email.',
        ],
        'welcome' => [
            'subject' => 'Your 11med account is ready',
            'line' => 'An account has been opened for you on 11med. Choose a password to sign in.',
            'action' => 'Choose your password',
            'expiry' => 'The link works once, for seven days.',
        ],
    ],
    'new_sign_in' => [
        'title' => 'New sign-in to your account',
        'body' => 'Your 11med account was signed in on :device on :time.',
        'advice' => 'If this was you, there is nothing to do. If it was not, change your password, which signs out every other device, and turn on two-step verification.',
        'unknown_device' => 'a new device',
    ],
];
