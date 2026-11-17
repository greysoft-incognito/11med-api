<?php

namespace App\Services\Guide;

use App\Services\Laboratories\GuideTestCatalogue;
use Illuminate\Container\Attributes\Bind;

/**
 * The test catalogue, so a `find_test` action never names a test no laboratory can list.
 */
#[Bind(GuideTestCatalogue::class)]
interface TestCatalogue
{
    /**
     * @param  list<string>  $codes
     * @return list<string> the codes the catalogue holds
     */
    public function known(array $codes): array;
}
