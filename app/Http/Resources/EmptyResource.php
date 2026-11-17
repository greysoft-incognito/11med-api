<?php

namespace App\Http\Resources;

use App\Support\Envelope;
use Illuminate\Http\JsonResponse;

/**
 * A success with `data: null`, for routes whose contract answers nothing.
 */
class EmptyResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct(null);
    }

    public function toResponse($request): JsonResponse
    {
        $keys = Envelope::successKeys($this->message);

        return new JsonResponse([
            'success' => true,
            'data' => null,
            'message' => $keys['message'],
            'timestamp' => $keys['timestamp'],
        ]);
    }
}
