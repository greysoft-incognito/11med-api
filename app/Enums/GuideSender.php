<?php

namespace App\Enums;

/**
 * Who wrote a guide message (app `zGuideMessage.sender`).
 */
enum GuideSender: string
{
    case User = 'user';
    case Guide = 'guide';
}
