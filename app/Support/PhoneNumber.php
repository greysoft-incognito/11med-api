<?php

namespace App\Support;

/**
 * Nigerian mobile numbers. `080…` and `+234 80…` are one number, stored in the local form.
 */
final class PhoneNumber
{
    public const PATTERN = '/^(0[789][01]\d{8}|\+234[789][01]\d{8})$/';

    public static function normalise(string $phone): string
    {
        return str_starts_with($phone, '+234') ? '0' . substr($phone, 4) : $phone;
    }

    /**
     * E.164, for the SMS gateway.
     */
    public static function international(string $phone): string
    {
        return '+234' . substr(self::normalise($phone), 1);
    }
}
