<?php

// What notifications written by the API say. A push names what happened, never what is wrong.
return [
    'missed_dose' => [
        'title' => 'Missed dose of :name',
        'body' => ':strength was due and has not been marked taken.',
    ],
];
