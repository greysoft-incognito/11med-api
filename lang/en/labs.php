<?php

// Pushes about laboratory requests and bookings. They say what happened and never name a test or a value.
return [
    'push' => [
        'request_title' => 'Your doctor has requested tests',
        'request_body' => ':doctor has asked for laboratory tests. Choose a laboratory in 11med.',
        'sample_collected_title' => 'Your sample has been collected',
        'sample_collected_body' => 'Booking :reference. The laboratory has your sample.',
        'processing_title' => 'The laboratory is working on your tests',
        'processing_body' => 'Booking :reference. We will tell you when the results are ready.',
        'results_ready_title' => 'Your laboratory results are ready',
        'results_ready_body' => 'Booking :reference. Open 11med to see them.',
        'cancelled_title' => 'The laboratory cancelled your booking',
        'cancelled_body' => 'Booking :reference. Any payment is refunded. Open 11med to see why.',
        'team_cancelled_title' => 'The 11med team cancelled your booking',
        'team_cancelled_body' => 'Booking :reference. Any payment is refunded. Open 11med to see why.',
    ],
];
