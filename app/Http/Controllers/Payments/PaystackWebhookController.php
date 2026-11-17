<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmptyResource;
use App\Services\Payments\PaystackWebhook;
use Illuminate\Http\Request;

class PaystackWebhookController extends Controller
{
    public function __construct(
        private readonly PaystackWebhook $webhook,
    ) {}

    public function __invoke(Request $request): EmptyResource
    {
        $this->webhook->handle($request->getContent(), $request->header('x-paystack-signature'));

        return new EmptyResource;
    }
}
