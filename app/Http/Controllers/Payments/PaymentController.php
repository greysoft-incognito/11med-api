<?php

namespace App\Http\Controllers\Payments;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\InitialisePaymentRequest;
use App\Http\Resources\Payments\PaymentResource;
use App\Services\Payments\Payments;
use App\Services\Payments\PaymentStarter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly Payments $payments,
        private readonly PaymentStarter $starter,
    ) {}

    public function store(InitialisePaymentRequest $request): JsonResponse
    {
        $payment = $this->starter->start($request->user(), $request->purpose(), $request->validated('target_id'), $request->channel());

        return (new PaymentResource($payment))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }

    public function show(Request $request, string $reference): PaymentResource
    {
        $payment = $this->payments->forPatient($request->user(), $reference);

        return new PaymentResource($payment);
    }
}
