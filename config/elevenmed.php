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

    // Two-step verification (M14): an authenticator app with single-use recovery codes. Required for
    // the team's accounts unless ADMIN_2FA_REQUIRED is false; optional for everyone else.
    'two_factor' => [
        'admin_required' => (bool) env('ADMIN_2FA_REQUIRED', true),
        'issuer' => env('TWO_FACTOR_ISSUER', '11med'),
        'challenge_minutes' => 5,
        'challenge_attempts' => 5,
        'recovery_codes' => 10,
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

    // Notifications (M7): remote push through APNs and FCM directly, with native tokens (app handover §8).
    // Logged until PUSH_ENABLED is true and a provider's credentials are set (HT-006).
    'push' => [
        'enabled' => (bool) env('PUSH_ENABLED', false),
        'android_channel' => 'updates',
        'apns' => [
            'key_path' => env('APNS_KEY_PATH'),
            'key_id' => env('APNS_KEY_ID'),
            'team_id' => env('APNS_TEAM_ID'),
            'bundle_id' => env('APNS_BUNDLE_ID', 'ng.elevenmed.app'),
            'production' => (bool) env('APNS_PRODUCTION', false),
        ],
        'fcm' => [
            'credentials_path' => env('FCM_CREDENTIALS_PATH'),
        ],
    ],

    // Account erasure (NDPR, app handover §3.1). What survives, severed from the person, pending a legal answer (HT-004).
    'erasure' => [
        'retained' => [
            'consent_records' => 'Records of the consents you gave, without your name or number',
            'account_erasures' => 'That an account was deleted, its role, when, and the reason you gave',
            'payments' => 'Payments you made: the amount, what for and when, without your name or number',
            'subscription_payments' => 'The payment references for premium the team recorded, without your name or number',
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

    // Partners (portal handover §6): applications per address an hour.
    'partners' => [
        'applications_per_hour' => 5,
    ],

    // Laboratories: bookings a patient may start in an hour, since each opens a payment.
    'laboratories' => [
        'bookings_per_hour' => 20,
    ],

    // Pharmacy: checkouts per patient an hour, each starting a gateway transaction.
    'pharmacy' => [
        'checkouts_per_hour' => 20,
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

    // Guide: conversations idle this long are deleted, a conversation keeps only its newest
    // messages, and a patient may send this many an hour (app handover §11).
    'guide' => [
        'retention_days' => (int) env('GUIDE_RETENTION_DAYS', 30),
        'max_messages' => 100,
        'messages_per_hour' => 60,
    ],

    // Payments (app handover §16). Paystack's keys are in config/services.php. The callback is the app's pay
    // screen; accounts with no email are given one at this domain for Paystack's sake.
    'payments' => [
        'callback_url' => env('PAYSTACK_CALLBACK_URL', 'elevenmed://pay'),
        'email_domain' => env('PAYMENTS_EMAIL_DOMAIN', 'customers.11med.ng'),
        'reference_prefix' => '11M-',
        'abandon_after_minutes' => (int) env('PAYMENTS_ABANDON_AFTER_MINUTES', 60),
        'check_every_seconds' => 15,
        'initialise_per_minute' => 10,

        // The PayableTargets class each module provides for its purpose; one not yet built finds nothing.
        'targets' => [
            'order' => OrderPayables::class,
            'lab_booking' => BookingPayables::class,
            'consultation' => ConsultationPayables::class,
        ],
    ],

    // Consultations (app spec §7, handover §2.3 and §13): two deadlines, run on the scheduler, both
    // refunded in full; slots in Africa/Lagos; payouts every 48 hours at the platform's commission.
    'consultations' => [
        'accept_minutes' => 5,
        'join_minutes' => 10,
        'scheduled_accept_max_hours' => 24,
        'scheduled_accept_before_minutes' => 60,
        'cancel_cutoff_minutes' => 60,
        'early_start_minutes' => 5,
        'follow_up_days' => 14,
        'prescription_valid_days' => 30,
        'commission_percent' => (float) env('CONSULTATION_COMMISSION_PERCENT', 15),
        'payout_interval_hours' => 48,
        'earnings_payouts' => 50,
    ],

    // Voice and video calls (app handover §15): a provider carries the media; the server makes rooms
    // and mints a token per participant. `mock` answers made-up rooms the app's stand-in opens.
    'calls' => [
        'provider' => env('CALL_PROVIDER', 'mock'),
        'token_margin_minutes' => 30,
        'daily' => [
            'api_key' => env('DAILY_API_KEY'),
            'api_url' => env('DAILY_API_URL', 'https://api.daily.co/v1'),
        ],
    ],

    // The client checkouts the route inventory is read from (`php artisan contract:routes`).
    'clients' => [
        'app' => env('ELEVENMED_APP_PATH', base_path('../11med')),
        'portals' => env('ELEVENMED_PORTALS_PATH', base_path('../11med-web')),
    ],

    // Routes the mocks register for development only; the handovers say not to build them. A doctor's
    // review is the admin portal's decision, never the doctor's own. The patient's prescription route
    // stands in for the doctor's own (M10).
    'mock_only_routes' => [
        'POST api/v1/consultations/{id}/accept',
        'POST api/v1/consultations/{id}/start',
        'POST api/v1/consultations/{id}/end',
        'POST api/v1/consultations/{id}/prescription',
        'POST api/v1/consultations/{id}/simulate',
        'POST api/v1/devices/{installId}/mock-deliveries',
        'POST api/v1/doctor/profile/review',
        'POST api/v1/labs/bookings/{bookingId}/advance',
        'POST api/v1/payments/{reference}/simulate',
        'POST api/v1/pharmacy/orders/{orderId}/advance',
        'POST api/v1/portal/admin/reset',
    ],
];
