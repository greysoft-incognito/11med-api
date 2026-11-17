<?php

namespace App\Enums;

enum SocialProvider: string
{
    case Google = 'google';
    case Apple = 'apple';

    public function keysUrl(): string
    {
        return match ($this) {
            self::Google => 'https://www.googleapis.com/oauth2/v3/certs',
            self::Apple => 'https://appleid.apple.com/auth/keys',
        };
    }

    /**
     * @return list<string>
     */
    public function issuers(): array
    {
        return match ($this) {
            self::Google => ['https://accounts.google.com', 'accounts.google.com'],
            self::Apple => ['https://appleid.apple.com'],
        };
    }

    public function audience(): ?string
    {
        return config("services.{$this->value}.client_id");
    }
}
