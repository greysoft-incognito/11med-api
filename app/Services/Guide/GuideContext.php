<?php

namespace App\Services\Guide;

/**
 * What the guide may read of the record (app handover §11): the first name, the names of the
 * conditions, and how many medications are tracked. Not allergies, doses or history.
 */
final readonly class GuideContext
{
    /**
     * @param  list<string>  $conditions
     */
    public function __construct(
        public ?string $firstName,
        public array $conditions,
        public int $medicationCount,
    ) {}
}
