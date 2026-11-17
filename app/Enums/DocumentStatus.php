<?php

namespace App\Enums;

/**
 * A doctor document's state (app `zDocumentStatus`).
 */
enum DocumentStatus: string
{
    case Missing = 'missing';
    case Uploaded = 'uploaded';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
