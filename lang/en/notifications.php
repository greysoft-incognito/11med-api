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

    // Portals (M16): the reference and what to do, never what was bought or tested.
    'pharmacy_order' => [
        'arrived_title' => 'New order :reference',
        'paid_body' => 'It is paid and waiting for you to start it.',
        'counter_body' => 'A pickup to be paid at the counter. Start it when you are ready.',
        'cancelled_title' => 'Order :reference was cancelled',
        'cancelled_body' => 'The patient cancelled it before you started. Do not prepare it.',
    ],
    'lab_booking' => [
        'arrived_title' => 'New booking :reference',
        'arrived_body' => 'It is paid and waiting for the sample.',
        'cancelled_title' => 'Booking :reference was cancelled',
        'cancelled_body' => 'The patient cancelled it before the sample was taken.',
    ],
    'team' => [
        'doctor_submitted' => ['title' => 'A doctor is waiting for review', 'body' => ':name submitted their documents.'],
        'partner_applied' => [
            'pharmacy_title' => 'A pharmacy applied',
            'laboratory_title' => 'A laboratory applied',
            'body' => ':name is waiting for a decision.',
        ],
        'refund_failed' => ['title' => 'A refund failed', 'body' => 'Paystack would not refund payment :reference. It needs the team.'],
    ],
];
