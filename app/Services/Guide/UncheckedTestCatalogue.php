<?php

namespace App\Services\Guide;

/**
 * Accepts every code until the catalogue exists.
 */
class UncheckedTestCatalogue implements TestCatalogue
{
    public function known(array $codes): array
    {
        return $codes;
    }
}
