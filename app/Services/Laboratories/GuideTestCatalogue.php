<?php

namespace App\Services\Laboratories;

use App\Models\LabTest;
use App\Services\Guide\TestCatalogue;

/**
 * The guide's view of the catalogue: which of the codes it names are tests a laboratory can list.
 */
class GuideTestCatalogue implements TestCatalogue
{
    public function known(array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        $held = LabTest::query()
            ->whereIn('code', array_map('strtoupper', $codes))
            ->pluck('code')
            ->all();

        return array_values(array_filter($codes, fn(string $code) => in_array(strtoupper($code), $held, true)));
    }
}
