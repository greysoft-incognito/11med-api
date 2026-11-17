<?php

namespace Tests\Feature\Laboratories;

use App\Services\Guide\TestCatalogue;
use Tests\Concerns\MakesLaboratories;
use Tests\TestCase;

class GuideTestCatalogueTest extends TestCase
{
    use MakesLaboratories;

    public function test_the_guide_keeps_only_codes_the_catalogue_holds(): void
    {
        $this->labTest('FBC');

        $catalogue = app(TestCatalogue::class);

        $this->assertSame(['FBC', 'fbc'], $catalogue->known(['FBC', 'NOPE', 'fbc']));
        $this->assertSame([], $catalogue->known([]));
    }
}
