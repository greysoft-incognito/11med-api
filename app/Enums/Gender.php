<?php

namespace App\Enums;

/**
 * `GENDERS` (app `src/constants/enums.ts`).
 */
enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case PreferNotToSay = 'prefer_not_to_say';
}
