<?php

namespace App\Enums;

/**
 * A medication's `with_food` (app `WITH_FOOD_OPTIONS`).
 */
enum WithFood: string
{
    case BeforeFood = 'before_food';
    case WithFood = 'with_food';
    case AfterFood = 'after_food';
    case NotSpecified = 'not_specified';
}
