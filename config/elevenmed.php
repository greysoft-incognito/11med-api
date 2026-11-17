<?php

return [
    // Calendar logic (slots, dose days, "today" on summaries) runs here; instants stay UTC.
    'timezone' => 'Africa/Lagos',

    // Access tokens live fifteen minutes; a refresh token rotates on use and lapses unused (app spec §2.1.4).
    'auth' => [
        'access_minutes' => 15,
        'refresh_days' => 30,
    ],

    // Phone and email codes (app spec §2.1.1). A fixed code lets staging run without SMS; production ignores it.
    'otp' => [
        'ttl_seconds' => 600,
        'max_resends' => 3,
        'resend_window_minutes' => 30,
        'max_attempts' => 5,
        'fixed_code' => env('OTP_FIXED_CODE'),
    ],

    // Failed sign-ins per phone number (app spec §2.1.4).
    'login' => [
        'lock_after' => 5,
        'lock_minutes' => 15,
        'hard_lock_after' => 10,
    ],

    // SMS go through the kit's gateway once enabled; until then they are logged (HT-002).
    'sms' => [
        'enabled' => (bool) env('SMS_ENABLED', false),
    ],

    // Where the portals live; reset and welcome emails link to `<url>/set-password?token=…`.
    'portals_url' => env('PORTALS_URL', 'http://localhost:3000'),

    // The policy version consent is recorded against when a client does not name one.
    'consent' => [
        'policy_version' => env('CONSENT_POLICY_VERSION', '2026-07-01'),
    ],

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
