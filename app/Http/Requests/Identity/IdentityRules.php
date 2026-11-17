<?php

namespace App\Http\Requests\Identity;

use App\Support\PhoneNumber;

/**
 * Rules the app's auth schemas share (`zNigerianPhone`, `zPassword`, six-digit codes).
 */
final class IdentityRules
{
    /**
     * @return list<string>
     */
    public static function phone(): array
    {
        return ['required', 'string', 'regex:' . PhoneNumber::PATTERN];
    }

    /**
     * At least eight characters, one capital and one digit (app spec §2.1.4).
     *
     * @return list<string>
     */
    public static function password(): array
    {
        return ['required', 'string', 'min:8', 'max:128', 'regex:/[A-Z]/', 'regex:/\d/'];
    }

    /**
     * @return list<string>
     */
    public static function code(): array
    {
        return ['required', 'string', 'size:6'];
    }

    /**
     * The consent the client captured, when it sends it (FE-006).
     *
     * @return array<string, list<string>>
     */
    public static function consent(): array
    {
        return [
            'consent' => ['sometimes', 'array'],
            'consent.policy_version' => ['required_with:consent', 'string', 'max:32'],
            'consent.accepted_at' => ['required_with:consent', 'date'],
        ];
    }
}
