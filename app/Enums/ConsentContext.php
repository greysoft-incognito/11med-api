<?php

namespace App\Enums;

/**
 * Where a consent was given (app `src/lib/consent.ts`).
 */
enum ConsentContext: string
{
    case Register = 'register';
    case SocialLink = 'social-link';
    case ProfileSharing = 'profile-sharing';
}
