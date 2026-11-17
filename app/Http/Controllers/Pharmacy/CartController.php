<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\UpdateCartRequest;
use App\Http\Resources\Pharmacy\CartResource;
use App\Services\Pharmacy\Cart;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private readonly Cart $cart,
    ) {}

    public function show(Request $request): CartResource
    {
        $cart = $this->cart->show($request->user());

        return new CartResource($cart);
    }

    public function update(UpdateCartRequest $request): CartResource
    {
        $cart = $this->cart->set($request->user(), $request->validated('drug_id'), $request->integer('quantity'));

        return new CartResource($cart);
    }
}
