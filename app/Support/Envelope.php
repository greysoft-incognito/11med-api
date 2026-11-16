<?php

namespace App\Support;

use App\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;

/**
 * The clients' response envelope: `{ success, data, message, timestamp }` on success,
 * `{ success: false, error: { code, message, field?, meta? }, timestamp }` on a refusal.
 */
final class Envelope
{
    /**
     * @return array{success: true, message: string, timestamp: string}
     */
    public static function successKeys(string $message): array
    {
        return [
            'success' => true,
            'message' => $message,
            'timestamp' => self::timestamp(),
        ];
    }

    /**
     * @param  array<string, string>  $headers
     */
    public static function failure(ApiException $e, array $headers = []): JsonResponse
    {
        $error = array_filter([
            'code' => $e->errorCode,
            'message' => $e->getMessage(),
            'field' => $e->field,
            'meta' => $e->meta,
        ], fn($value) => $value !== null);

        $body = [
            'success' => false,
            'error' => $error,
            'timestamp' => self::timestamp(),
        ];

        return new JsonResponse($body, $e->status->value, $headers);
    }

    public static function timestamp(): string
    {
        return now()
            ->utc()
            ->toIso8601ZuluString('millisecond');
    }
}
