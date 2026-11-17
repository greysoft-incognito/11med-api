<?php

namespace App\Enums;

/**
 * Brought by the pharmacy's rider, or collected at its counter (app `zFulfilment`).
 */
enum Fulfilment: string
{
    case Delivery = 'delivery';
    case Pickup = 'pickup';
}
