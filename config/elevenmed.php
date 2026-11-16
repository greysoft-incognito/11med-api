<?php

return [
    // Calendar logic (slots, dose days, "today" on summaries) runs here; instants stay UTC.
    'timezone' => 'Africa/Lagos',

    // The client checkouts the route inventory is read from (`php artisan contract:routes`).
    'clients' => [
        'app' => env('ELEVENMED_APP_PATH', base_path('../11med')),
        'portals' => env('ELEVENMED_PORTALS_PATH', base_path('../11med-web')),
    ],

    // Routes the mocks register for development only; the handovers say not to build them.
    'mock_only_routes' => [
        'POST api/v1/consultations/{id}/accept',
        'POST api/v1/consultations/{id}/start',
        'POST api/v1/consultations/{id}/end',
        'POST api/v1/consultations/{id}/simulate',
        'POST api/v1/devices/{installId}/mock-deliveries',
        'POST api/v1/labs/bookings/{bookingId}/advance',
        'POST api/v1/payments/{reference}/simulate',
        'POST api/v1/pharmacy/orders/{orderId}/advance',
        'POST api/v1/portal/admin/reset',
    ],
];
