<?php

// Push copy for consultations (app handover §8.2): what happened, never what is wrong.
return [
    'requested' => [
        'title' => 'New consultation request',
        'body' => 'A patient is waiting. Accept by :deadline.',
    ],
    'accepted' => [
        'title' => ':doctor accepted your consultation',
        'body' => 'Open 11med to join when they are ready.',
    ],
    'declined' => [
        'title' => ':doctor could not take your consultation',
        'body' => 'Your payment is refunded in full. You can book another doctor.',
    ],
    'withdrawn' => [
        'title' => 'Your consultation was cancelled',
        'body' => ':doctor is no longer taking consultations. Your payment is refunded in full.',
    ],
    'patient_cancelled' => [
        'title' => 'A patient cancelled a consultation',
        'body' => 'The session is off and the patient is refunded.',
    ],
    'request_expired' => [
        'title' => 'A consultation request expired',
        'body' => 'It was not accepted in time, so the patient was refunded.',
    ],
    'prescribed' => [
        'title' => ':doctor sent you a prescription',
        'body' => 'Review it before anything is added to your medications.',
    ],
];
