<?php

namespace Tests\Feature\Pharmacy;

use App\Enums\PartnerKind;
use App\Models\Pharmacy;
use App\Models\User;
use App\Services\Pharmacy\PrescribedDrugs;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesPartners;
use Tests\Concerns\MakesPharmacyOrders;
use Tests\TestCase;

class StoreTest extends TestCase
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

    public function test_pharmacies_rank_the_patients_state_first_then_rating_with_the_unrated_last(): void
    {
        [$abuja] = $this->approvedPartner(PartnerKind::Pharmacy, ['name' => 'Abuja Rated', 'state' => 'FCT']);
        [$unrated] = $this->approvedPartner(PartnerKind::Pharmacy, ['name' => 'Lagos Unrated', 'state' => 'Lagos']);
        [$rated] = $this->approvedPartner(PartnerKind::Pharmacy, ['name' => 'Lagos Rated', 'state' => 'Lagos']);
        [$suspended] = $this->approvedPartner(PartnerKind::Pharmacy, ['name' => 'Lagos Suspended', 'state' => 'Lagos']);
        $this->approvedPartner(PartnerKind::Laboratory, ['name' => 'A Laboratory', 'state' => 'Lagos']);
        $this->postJson('api/v1/partners/apply', $this->application(['name' => 'Lagos Pending', 'state' => 'Lagos']))->assertCreated();

        Pharmacy::query()->where('organisation_id', $abuja->id)->update(['rating' => 4.9, 'rating_count' => 12]);
        Pharmacy::query()->where('organisation_id', $rated->id)->update(['rating' => 4.1, 'rating_count' => 3]);
        $this->suspend($suspended);
        $this->drug($rated, ['in_stock' => true]);
        $this->drug($rated, ['in_stock' => false]);

        $patient = $this->patient('Lagos');

        $first = $this->actingAs($patient)
            ->getJson('api/v1/pharmacy/pharmacies?limit=2')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.items.0.name', 'Lagos Rated')
            ->assertJsonPath('data.items.0.rating', 4.1)
            ->assertJsonPath('data.items.0.in_stock_count', 1)
            ->assertJsonPath('data.items.1.name', 'Lagos Unrated')
            ->json('data');

        $this->actingAs($patient)
            ->getJson('api/v1/pharmacy/pharmacies?limit=2&cursor=' . $first['next_cursor'])
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Abuja Rated')
            ->assertJsonPath('data.next_cursor', null);

        $this->actingAs($this->patient())
            ->getJson('api/v1/pharmacy/pharmacies')
            ->assertJsonPath('data.items.0.name', 'Abuja Rated');

        $this->actingAs($patient)
            ->getJson('api/v1/pharmacy/pharmacies?q=unrated')
            ->assertJsonPath('data.total', 1);

        $this->actingAs($patient)
            ->getJson('api/v1/pharmacy/pharmacies?q=fct')
            ->assertJsonPath('data.total', 1);

        $this->actingAs($patient)
            ->getJson("api/v1/pharmacy/pharmacies/{$unrated->uuid}")
            ->assertOk()
            ->assertJsonPath('data.id', $unrated->uuid);

        $this->actingAs($patient)
            ->getJson("api/v1/pharmacy/pharmacies/{$suspended->uuid}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'PHARMACY_NOT_FOUND');
    }

    public function test_medicines_are_in_stock_by_default_filtered_and_delisted_with_their_pharmacy(): void
    {
        [$lagos] = $this->approvedPartner(PartnerKind::Pharmacy, ['name' => 'Lagos', 'state' => 'Lagos']);
        [$kano] = $this->approvedPartner(PartnerKind::Pharmacy, ['name' => 'Kano', 'state' => 'Kano']);

        $kanoAmox = $this->drug($kano, ['name' => 'Amoxicillin']);
        $lagosAmox = $this->drug($lagos, ['name' => 'Amoxicillin', 'brand_name' => 'Amoxil', 'requires_prescription' => true]);
        $this->drug($lagos, ['name' => 'Paracetamol', 'category' => 'analgesics', 'in_stock' => false]);

        $patient = $this->patient('Lagos');

        $this->actingAs($patient)
            ->getJson('api/v1/pharmacy/drugs')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.0.id', $lagosAmox->uuid)
            ->assertJsonPath('data.items.0.pharmacy_state', 'Lagos')
            ->assertJsonPath('data.items.1.id', $kanoAmox->uuid);

        $this->actingAs($patient)
            ->getJson('api/v1/pharmacy/drugs?in_stock=false&category=analgesics')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.name', 'Paracetamol');

        $this->actingAs($patient)
            ->getJson('api/v1/pharmacy/drugs?requires_prescription=true')
            ->assertJsonPath('data.total', 1);

        $this->actingAs($patient)
            ->getJson("api/v1/pharmacy/drugs?pharmacy_id={$kano->uuid}&q=amox")
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.id', $kanoAmox->uuid);

        $this->suspend($kano);

        $this->actingAs($patient)
            ->getJson('api/v1/pharmacy/drugs')
            ->assertJsonPath('data.total', 1);

        $this->actingAs($patient)
            ->getJson("api/v1/pharmacy/drugs/{$kanoAmox->uuid}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'DRUG_NOT_FOUND');

        $this->actingAs($patient)
            ->getJson("api/v1/pharmacy/drugs/{$lagosAmox->uuid}")
            ->assertOk()
            ->assertJsonPath('data.brand_name', 'Amoxil');
    }

    public function test_the_cart_prices_delivery_per_pharmacy_and_flags_unprescribed_lines(): void
    {
        [$lagos] = $this->approvedPartner(PartnerKind::Pharmacy, ['name' => 'Lagos']);
        [$kano] = $this->approvedPartner(PartnerKind::Pharmacy, ['name' => 'Kano']);
        Pharmacy::query()->where('organisation_id', $lagos->id)->update(['free_delivery_over_kobo' => 500000]);

        $amox = $this->drug($lagos, ['name' => 'Amoxicillin', 'price_kobo' => 300000, 'requires_prescription' => true]);
        $vitamin = $this->drug($kano, ['name' => 'Vitamin C', 'price_kobo' => 100000]);
        $out = $this->drug($kano, ['in_stock' => false]);

        $prescribed = new class implements PrescribedDrugs
        {
            /** @var list<string> */
            public array $names = [];

            public function names(User $patient): array
            {
                return $this->names;
            }
        };

        $this->app->instance(PrescribedDrugs::class, $prescribed);

        $patient = $this->patient();

        $this->addToCart($patient, $amox, 2);
        $this->addToCart($patient, $vitamin);

        $this->actingAs($patient)
            ->getJson('api/v1/pharmacy/cart')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.subtotal_kobo', 700000)
            ->assertJsonPath('data.delivery_fee_kobo', 150000)
            ->assertJsonPath('data.total_kobo', 850000)
            ->assertJsonPath('data.blocked_items', [$amox->uuid]);

        $prescribed->names = ['amoxicillin'];

        $this->actingAs($patient)
            ->patchJson('api/v1/pharmacy/cart', ['drug_id' => $vitamin->uuid, 'quantity' => 0])
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.blocked_items', [])
            ->assertJsonPath('data.delivery_fee_kobo', 0);

        $this->actingAs($patient)
            ->patchJson('api/v1/pharmacy/cart', ['drug_id' => $out->uuid, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'OUT_OF_STOCK');

        $this->actingAs($patient)
            ->patchJson('api/v1/pharmacy/cart', ['drug_id' => '0190a0a0-0000-7000-8000-000000000000', 'quantity' => 1])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'DRUG_NOT_FOUND');

        $this->actingAs($patient)
            ->patchJson('api/v1/pharmacy/cart', ['drug_id' => $amox->uuid, 'quantity' => -1])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'quantity');

        $this->suspend($lagos);

        $this->actingAs($patient)
            ->getJson('api/v1/pharmacy/cart')
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.total_kobo', 0);
    }

    public function test_the_store_is_the_patients(): void
    {
        [, $pharmacy] = $this->approvedPartner();

        $this->getJson('api/v1/pharmacy/drugs')->assertUnauthorized();

        $this->actingAs($pharmacy)
            ->getJson('api/v1/pharmacy/cart')
            ->assertForbidden();
    }
}
