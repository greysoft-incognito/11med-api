<?php

namespace App\Events\Doctors;

use App\Models\DoctorDocument;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The team accepted or rejected one of a doctor's documents.
 */
class DoctorDocumentReviewed
{
    use Dispatchable;

    public function __construct(
        public readonly DoctorDocument $document,
    ) {}
}
