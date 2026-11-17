<?php

// What notifications written by the API say. A push names what happened, never what is wrong.
return [
    'missed_dose' => [
        'title' => 'Missed dose of :name',
        'body' => ':strength was due and has not been marked taken.',
    ],

    // Doctors (M8)
    'doctor_review' => [
        'approved' => ['title' => 'Your 11med profile is verified', 'body' => 'Patients can now find and book you.'],
        'rejected' => ['title' => 'Your application needs changes', 'body' => 'Open 11med to see what to change, then submit again.'],
        'suspended' => ['title' => 'Your 11med profile is suspended', 'body' => 'Open 11med to see why, or contact the team.'],
        'reinstated' => ['title' => 'Your 11med profile is active again', 'body' => 'Patients can find and book you again.'],
    ],
    'doctor_document_rejected' => [
        'title' => 'A document needs replacing',
        'body' => 'Open 11med to see why, then upload it again.',
    ],
];
