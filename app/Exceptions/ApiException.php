<?php

namespace App\Exceptions;

use App\Enums\HttpStatus;
use RuntimeException;

/**
 * A refusal the clients' contracts name: a status, an UPPER_SNAKE code, an optional
 * request field and optional structured detail. The message defaults to `errors.<CODE>`.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public readonly HttpStatus $status,
        public readonly string $errorCode,
        ?string $message = null,
        public readonly ?string $field = null,
        public readonly ?array $meta = null,
    ) {
        parent::__construct($message ?? self::messageFor($errorCode), $status->value);
    }

    public static function messageFor(string $code): string
    {
        $key = "errors.{$code}";
        $message = __($key);

        return $message === $key ? __('errors.DEFAULT') : $message;
    }

    public static function badRequest(string $code, ?string $field = null, ?string $message = null): static
    {
        return new static(HttpStatus::BAD_REQUEST, $code, $message, $field);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public static function unauthorized(string $code = 'UNAUTHENTICATED', ?array $meta = null): static
    {
        return new static(HttpStatus::UNAUTHORIZED, $code, null, null, $meta);
    }

    public static function forbidden(string $code = 'FORBIDDEN'): static
    {
        return new static(HttpStatus::FORBIDDEN, $code);
    }

    public static function notFound(string $code = 'NOT_FOUND', ?string $field = null): static
    {
        return new static(HttpStatus::NOT_FOUND, $code, null, $field);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function locked(string $code, array $meta = []): static
    {
        return new static(HttpStatus::LOCKED, $code, null, null, $meta);
    }

    public static function conflict(string $code, ?string $field = null): static
    {
        return new static(HttpStatus::CONFLICT, $code, null, $field);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public static function unprocessable(string $code, ?string $field = null, ?array $meta = null): static
    {
        return new static(HttpStatus::UNPROCESSABLE_ENTITY, $code, null, $field, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function tooManyRequests(string $code, int $retryAfterMs, array $meta = []): static
    {
        return new static(HttpStatus::TOO_MANY_REQUESTS, $code, null, null, ['retry_after_ms' => $retryAfterMs] + $meta);
    }
}
