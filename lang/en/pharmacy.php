<?php

// What the patient is told as an order moves (app handover §8.2): what happened, never what was bought.
return [
    'push' => [
        'paid' => [
            'title' => 'Payment received',
            'body' => 'Order :reference is paid. The pharmacy has it now.',
        ],
        'preparing' => [
            'title' => 'The pharmacy is preparing your order',
            'delivery' => 'Order :reference. We will tell you when the rider picks it up.',
            'pickup' => 'Order :reference. We will tell you when it is ready to collect.',
        ],
        'ready' => [
            'delivery_title' => 'Your order is on its way',
            'delivery' => 'Order :reference. :rider has picked it up, about :minutes minutes away. Someone needs to be there to receive it.',
            'pickup_title' => 'Your order is ready for pickup',
            'pickup' => 'Order :reference. Collect it from :pharmacy. Show the reference at the counter.',
        ],
        'completed' => [
            'delivery_title' => 'Your order was delivered',
            'pickup_title' => 'Your order was collected',
            'body' => 'Order :reference. Tell us if anything is wrong with it.',
        ],
        'cancelled' => [
            'title' => 'The pharmacy cancelled your order',
            'body' => 'Order :reference. :reason',
        ],
    ],
];
