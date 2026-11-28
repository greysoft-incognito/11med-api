<?php

namespace App\Enums;

/**
 * A value the team may change at runtime from the admin portal. Each one overrides the config
 * value at its path; unset, the config file's value (and its env variable) stands.
 */
enum PlatformSetting: string
{
    case FreeMedications = 'free_tier_medications';
    case FreeHistoryDays = 'free_tier_history_days';
    case FreeReadingsDays = 'free_tier_readings_days';
    case FreeCaregivers = 'free_tier_caregivers';
    case PremiumCaregivers = 'premium_caregivers';
    case CommissionPercent = 'consultation_commission_percent';
    case OtpTtlSeconds = 'otp_ttl_seconds';
    case OtpMaxResends = 'otp_max_resends';
    case OtpResendWindowMinutes = 'otp_resend_window_minutes';
    case OtpMaxAttempts = 'otp_max_attempts';
    case LoginLockAfter = 'login_lock_after';
    case LoginLockMinutes = 'login_lock_minutes';
    case AdminTwoFactorRequired = 'admin_two_factor_required';

    public function configPath(): string
    {
        return match ($this) {
            self::FreeMedications => 'elevenmed.free_tier.medications',
            self::FreeHistoryDays => 'elevenmed.free_tier.history_days',
            self::FreeReadingsDays => 'elevenmed.free_tier.readings_days',
            self::FreeCaregivers => 'elevenmed.caregivers.free',
            self::PremiumCaregivers => 'elevenmed.caregivers.premium',
            self::CommissionPercent => 'elevenmed.consultations.commission_percent',
            self::OtpTtlSeconds => 'elevenmed.otp.ttl_seconds',
            self::OtpMaxResends => 'elevenmed.otp.max_resends',
            self::OtpResendWindowMinutes => 'elevenmed.otp.resend_window_minutes',
            self::OtpMaxAttempts => 'elevenmed.otp.max_attempts',
            self::LoginLockAfter => 'elevenmed.login.lock_after',
            self::LoginLockMinutes => 'elevenmed.login.lock_minutes',
            self::AdminTwoFactorRequired => 'elevenmed.two_factor.admin_required',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::FreeMedications, self::FreeHistoryDays, self::FreeReadingsDays, self::FreeCaregivers, self::PremiumCaregivers => 'free_tier',
            self::CommissionPercent => 'consultations',
            self::OtpTtlSeconds, self::OtpMaxResends, self::OtpResendWindowMinutes, self::OtpMaxAttempts => 'codes',
            self::LoginLockAfter, self::LoginLockMinutes, self::AdminTwoFactorRequired => 'sign_in',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::FreeMedications => 'Active medications on the free tier',
            self::FreeHistoryDays => 'Days of dose history on the free tier',
            self::FreeReadingsDays => 'Days of readings on the free tier',
            self::FreeCaregivers => 'Caregivers on the free tier',
            self::PremiumCaregivers => 'Caregivers on premium',
            self::CommissionPercent => 'Commission on consultations, percent',
            self::OtpTtlSeconds => 'Seconds a code stays valid',
            self::OtpMaxResends => 'Codes a number may be sent in the window',
            self::OtpResendWindowMinutes => 'Code window, minutes',
            self::OtpMaxAttempts => 'Wrong tries before a code is spent',
            self::LoginLockAfter => 'Failed sign-ins before a lock',
            self::LoginLockMinutes => 'Lock length, minutes',
            self::AdminTwoFactorRequired => 'Team accounts must use two-step verification',
        };
    }

    /**
     * `integer`, `number` (decimals allowed) or `boolean`.
     */
    public function type(): string
    {
        return match ($this) {
            self::CommissionPercent => 'number',
            self::AdminTwoFactorRequired => 'boolean',
            default => 'integer',
        };
    }

    /**
     * The inclusive bounds a value must sit within, null for a boolean.
     *
     * @return array{0: int, 1: int}|null
     */
    public function bounds(): ?array
    {
        return match ($this) {
            self::FreeMedications => [1, 50],
            self::FreeHistoryDays, self::FreeReadingsDays => [1, 365],
            self::FreeCaregivers => [0, 10],
            self::PremiumCaregivers => [1, 20],
            self::CommissionPercent => [0, 50],
            self::OtpTtlSeconds => [60, 1800],
            self::OtpMaxResends => [1, 10],
            self::OtpResendWindowMinutes => [5, 240],
            self::OtpMaxAttempts => [1, 10],
            self::LoginLockAfter => [3, 9],
            self::LoginLockMinutes => [1, 1440],
            self::AdminTwoFactorRequired => null,
        };
    }

    /**
     * The value as the config file reads it, from the string it is stored as.
     */
    public function cast(string $stored): int|float|bool
    {
        return match ($this->type()) {
            'boolean' => $stored === '1',
            'number' => (float) $stored,
            default => (int) $stored,
        };
    }

    /**
     * A value as stored: booleans as `1` or `0`, numbers as written.
     */
    public function store(int|float|bool $value): string
    {
        return is_bool($value) ? ($value ? '1' : '0') : (string) $value;
    }

    /**
     * The row key in the dbconfig `configurations` table.
     */
    public function storageKey(): string
    {
        return 'platform.' . $this->value;
    }
}
