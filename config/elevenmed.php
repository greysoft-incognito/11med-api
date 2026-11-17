<?php

use App\Services\Consultations\ConsultationPayables;
use App\Services\Laboratories\BookingPayables;
use App\Services\Pharmacy\OrderPayables;

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

    // Account erasure (NDPR, app handover §3.1). What survives, severed from the person, pending a legal answer (HT-004).
    'erasure' => [
        'retained' => [
            'consent_records' => 'Records of the consents you gave, without your name or number',
            'account_erasures' => 'That an account was deleted, its role, when, and the reason you gave',
        ],
        'retention_years' => 6,
    ],

    // Uploads (app handover §12, portal handover §3). Pictures arrive resized; the server checks,
    // re-encodes without metadata and stores. Private purposes are served by signed links.
    'media' => [
        'public_disk' => env('MEDIA_PUBLIC_DISK', 'public'),
        'private_disk' => env('MEDIA_PRIVATE_DISK', 'local'),
        'max_bytes' => 5 * 1024 * 1024,
        'max_edge' => 1600,
        'signed_minutes' => 15,
        'orphan_hours' => 24,
        'uploads_per_hour' => 120,
        'public_uploads_per_hour' => 10,
    ],

    // The public emergency page a QR code opens (portal `/profile/view`), and its limits per token and address.
    'emergency_url' => env('EMERGENCY_URL', 'https://11med.ng/profile/view'),
    'emergency' => [
        'opens_per_hour_per_token' => 30,
        'opens_per_hour_per_address' => 60,
    ],

    // Caregivers (app spec §6.5): the store link the invitation SMS carries, and the free and premium caps.
    'app_link' => env('APP_DOWNLOAD_URL', 'https://11med.ng/app'),
    // Payments: the PayableTargets class each module provides for its purpose (M9 resolves them).
    'payments' => [
        'targets' => [
            'order' => OrderPayables::class,
            'lab_booking' => BookingPayables::class,
            'consultation' => ConsultationPayables::class,
        ],
    ],

    // The free tier's limits (app CLAUDE.md "Free vs premium gates"); premium lifts all three.
    'free_tier' => [
        'medications' => 3,
        'history_days' => 7,
        'readings_days' => 7,
    ],

    'caregivers' => [
        'free' => 1,
        'premium' => 5,
        'invites_per_day' => 10,
        'accept_attempts_per_hour' => 10,
    ],

    // Medications and readings (M6): the dose rules (app `constants/dosing.ts`), the longest window
    // any list or score reads, and the compliance report's signed link and file life.
    'medications' => [
        'missed_after_minutes' => 60,
        'max_snoozes' => 2,
        'missed_notice_days' => 2,
        'max_window_days' => 365,
        'batch_max' => 200,
        'future_tolerance_minutes' => 5,
        'report_disk' => env('REPORT_DISK', 'local'),
        'report_minutes' => 15,
        'report_keep_hours' => 24,
        'reports_per_hour' => 10,
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
