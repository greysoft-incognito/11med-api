<?php

namespace App\Enums;

/**
 * An emergency contact's `RELATIONSHIPS` (app `src/constants/enums.ts`).
 */
enum Relationship: string
{
    case Spouse = 'spouse';
    case Parent = 'parent';
    case Sibling = 'sibling';
    case Child = 'child';
    case Friend = 'friend';
    case Other = 'other';
}
