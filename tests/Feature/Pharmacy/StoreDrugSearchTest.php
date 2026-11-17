<?php

namespace Tests\Feature\Pharmacy;

use App\Enums\PartnerKind;
use App\Services\Guide\StoreSearch;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesPartners;
use Tests\Concerns\MakesPharmacyOrders;
use Tests\TestCase;

class StoreDrugSearchTest extends TestCase
{
    use MakesPartners;
    use MakesPharmacyOrders;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Notification::fake();
    }

    public function test_the_guide_finds_a_listed_medicine_by_its_longest_name(): void
    {
        [$listed] = $this->approvedPartner(PartnerKind::Pharmacy);
        [$suspended] = $this->approvedPartner(PartnerKind::Pharmacy);

        $this->drug($listed, ['name' => 'Coartem']);
        $this->drug($listed, ['name' => 'Coartem Dispersible']);
        $this->drug($suspended, ['name' => 'Amlodipine']);
        $this->suspend($suspended);

        $search = app(StoreSearch::class);

        $this->assertSame('Coartem Dispersible', $search->drugMentionedIn('Where can I buy COARTEM dispersible?'));
        $this->assertSame('Coartem', $search->drugMentionedIn('price of coartem'));
        $this->assertNull($search->drugMentionedIn('I need amlodipine'));
        $this->assertNull($search->drugMentionedIn('something for a headache'));
    }
}
