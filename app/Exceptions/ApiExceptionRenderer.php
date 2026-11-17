<?php

namespace App\Exceptions;

use App\Enums\HttpStatus;
use App\Services\Identity\BearerToken;
use App\Support\Envelope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Renders every exception under /api/v1 as the clients' error envelope.
 */
final class ApiExceptionRenderer
{
    public static function render(Throwable $e): JsonResponse
    {
        $error = self::classify($e);

        if ($error->status->value >= 500 && config('app.debug')) {
            $error = new ApiException($error->status, $error->errorCode, $e->getMessage(), null, [
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

        return Envelope::failure($error, $headers);
    }

    private static function classify(Throwable $e): ApiException
    {
        return match (true) {
            $e instanceof ApiException => $e,
            $e instanceof ValidationException => self::validation($e),
            $e instanceof AuthenticationException => ApiException::unauthorized(
                BearerToken::isExpired(request()) ? 'TOKEN_EXPIRED' : 'UNAUTHENTICATED'
            ),
            $e instanceof AuthorizationException,
            $e instanceof UnauthorizedException => ApiException::forbidden(),
            $e instanceof ModelNotFoundException => ApiException::notFound(self::notFoundCode($e)),
            $e instanceof NotFoundHttpException => ApiException::notFound(),
            $e instanceof MethodNotAllowedHttpException => new ApiException(HttpStatus::METHOD_NOT_ALLOWED, 'METHOD_NOT_ALLOWED'),
            $e instanceof ThrottleRequestsException => self::throttled($e),
            $e instanceof PostTooLargeException => new ApiException(HttpStatus::PAYLOAD_TOO_LARGE, 'FILE_TOO_LARGE', null, 'file'),
            $e instanceof InvalidSignatureException => ApiException::forbidden('LINK_EXPIRED'),
            $e instanceof HttpExceptionInterface => self::http($e),
            default => new ApiException(HttpStatus::SERVER_ERROR, 'SERVER_ERROR'),
        };
    }

    /**
     * The first failing field, as the forms map one error to one input.
     */
    private static function validation(ValidationException $e): ApiException
    {
        $errors = $e->errors();
        $field = array_key_first($errors);

        return ApiException::badRequest('VALIDATION_FAILED', $field, $errors[$field][0] ?? null);
    }

    /**
     * A model names its own code with a `NOT_FOUND` constant (`ORDER_NOT_FOUND`).
     */
    private static function notFoundCode(ModelNotFoundException $e): string
    {
        $constant = $e->getModel() . '::NOT_FOUND';

        return defined($constant) ? constant($constant) : 'NOT_FOUND';
    }

    private static function throttled(ThrottleRequestsException $e): ApiException
    {
        $seconds = (int) ($e->getHeaders()['Retry-After'] ?? 60);

        return ApiException::tooManyRequests('RATE_LIMITED', $seconds * 1000);
    }

    private static function http(HttpExceptionInterface $e): ApiException
    {
        $status = HttpStatus::tryFrom($e->getStatusCode()) ?? HttpStatus::SERVER_ERROR;
        $code = preg_match('/^[A-Z][A-Z0-9_]+$/', $e->getMessage()) === 1 ? $e->getMessage() : $status->name;

        return new ApiException($status, $code);
    }
}
