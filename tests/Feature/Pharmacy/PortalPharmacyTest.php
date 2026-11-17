<?php

namespace Tests\Feature\Pharmacy;

use App\Enums\MediaPurpose;
use App\Enums\PartnerKind;
use App\Enums\Role;
use App\Models\Media;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesPartners;
use Tests\Concerns\MakesPharmacyOrders;
use Tests\TestCase;

class PortalPharmacyTest extends TestCase
{
    use MakesPartners;
    use MakesPharmacyOrders;

    private Organisation $organisation;

    private User $account;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Notification::fake();

        [$this->organisation, $this->account] = $this->approvedPartner(PartnerKind::Pharmacy, ['name' => 'Medplus Ikeja', 'state' => 'Lagos']);
    }

    public function test_approval_opens_the_store_with_the_default_delivery_fee(): void
    {
        $this->actingAs($this->account)
            ->getJson('api/v1/portal/pharmacy')
            ->assertOk()
            ->assertJsonPath('data.id', $this->organisation->uuid)
            ->assertJsonPath('data.name', 'Medplus Ikeja')
            ->assertJsonPath('data.delivery_fee_kobo', 150000)
            ->assertJsonPath('data.free_delivery_over_kobo', null)
            ->assertJsonPath('data.rating', null)
            ->assertJsonPath('data.rating_count', 0)
            ->assertJsonPath('data.in_stock_count', 0)
            ->assertJsonPath('data.logo_url', null);
    }

    public function test_settings_change_the_listing_the_pictures_and_the_delivery_terms(): void
    {
        $logo = $this->picture($this->account, MediaPurpose::PharmacyLogo);
        $cover = $this->picture($this->account, MediaPurpose::PharmacyCover);

        $this->actingAs($this->account)
            ->patchJson('api/v1/portal/pharmacy', $this->settings(['logo_url' => $logo, 'cover_url' => $cover]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Medplus Lekki')
            ->assertJsonPath('data.city', 'Lekki')
            ->assertJsonPath('data.logo_url', $logo)
            ->assertJsonPath('data.cover_url', $cover)
            ->assertJsonPath('data.free_delivery_over_kobo', 2000000)
            ->assertJsonPath('data.delivery_minutes', 45);

        $this->assertSame('Medplus Lekki', $this->organisation->refresh()->name);

        $this->actingAs($this->account)
            ->patchJson('api/v1/portal/pharmacy', $this->settings(['logo_url' => $logo, 'cover_url' => null]))
            ->assertOk()
            ->assertJsonPath('data.logo_url', $logo)
            ->assertJsonPath('data.cover_url', null);

        $this->assertSame(0, Media::query()->where('purpose', MediaPurpose::PharmacyCover)->withTrashed()->count());
    }

    public function test_settings_refuse_a_picture_the_account_did_not_upload_for_that_purpose(): void
    {
        $other = $this->picture($this->account, MediaPurpose::PharmacyCover);

        $this->actingAs($this->account)
            ->patchJson('api/v1/portal/pharmacy', $this->settings(['logo_url' => $other]))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'MEDIA_NOT_FOUND')
            ->assertJsonPath('error.field', 'logo_url');

        $this->actingAs($this->account)
            ->patchJson('api/v1/portal/pharmacy', $this->settings(['delivery_fee_kobo' => -1]))
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'delivery_fee_kobo');
    }

    public function test_medicines_are_added_listed_edited_and_removed(): void
    {
        $image = $this->picture($this->account, MediaPurpose::DrugImage);

        $created = $this->actingAs($this->account)
            ->postJson('api/v1/portal/pharmacy/drugs', $this->drugBody(['name' => 'Amoxicillin', 'nafdac_number' => 'A4-1234', 'image_url' => $image, 'description' => '']))
            ->assertCreated()
            ->assertJsonPath('data.name', 'Amoxicillin')
            ->assertJsonPath('data.description', '')
            ->assertJsonPath('data.image_url', $image)
            ->assertJsonPath('data.pharmacy_id', $this->organisation->uuid)
            ->assertJsonPath('data.pharmacy_name', 'Medplus Ikeja')
            ->assertJsonPath('data.pharmacy_state', 'Lagos')
            ->json('data');

        $this->actingAs($this->account)
            ->postJson('api/v1/portal/pharmacy/drugs', $this->drugBody(['name' => 'Paracetamol', 'nafdac_number' => '04-5678', 'in_stock' => false]))
            ->assertCreated();

        $this->actingAs($this->account)
            ->getJson('api/v1/portal/pharmacy/drugs')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.0.name', 'Amoxicillin');

        $this->actingAs($this->account)
            ->getJson('api/v1/portal/pharmacy/drugs?stock=out')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.name', 'Paracetamol');

        $this->actingAs($this->account)
            ->getJson('api/v1/portal/pharmacy/drugs?q=a4-12')
            ->assertJsonPath('data.total', 1);

        $this->actingAs($this->account)
            ->patchJson("api/v1/portal/pharmacy/drugs/{$created['id']}", $this->drugBody(['name' => 'Amoxicillin', 'nafdac_number' => 'A4-1234', 'image_url' => $image, 'price_kobo' => 250000]))
            ->assertOk()
            ->assertJsonPath('data.price_kobo', 250000)
            ->assertJsonPath('data.image_url', $image);

        $this->actingAs($this->account)
            ->getJson('api/v1/portal/pharmacy')
            ->assertJsonPath('data.in_stock_count', 1);

        $this->actingAs($this->account)
            ->getJson('api/v1/portal/pharmacy/summary')
            ->assertOk()
            ->assertJsonPath('data.out_of_stock', 1)
            ->assertJsonPath('data.new_orders', 0)
            ->assertJsonPath('data.revenue_today_kobo', 0);

        $this->actingAs($this->account)
            ->deleteJson("api/v1/portal/pharmacy/drugs/{$created['id']}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->actingAs($this->account)
            ->getJson("api/v1/portal/pharmacy/drugs/{$created['id']}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'DRUG_NOT_FOUND');
    }

    public function test_a_nafdac_number_is_once_per_pharmacy_and_the_picture_must_be_its_own(): void
    {
        $this->actingAs($this->account)
            ->postJson('api/v1/portal/pharmacy/drugs', $this->drugBody(['nafdac_number' => 'A4-1234']))
            ->assertCreated();

        $this->actingAs($this->account)
            ->postJson('api/v1/portal/pharmacy/drugs', $this->drugBody(['nafdac_number' => 'a4-1234']))
            ->assertConflict()
            ->assertJsonPath('error.code', 'DUPLICATE_NAFDAC')
            ->assertJsonPath('error.field', 'nafdac_number');

        [, $elsewhere] = $this->approvedPartner();

        $this->actingAs($elsewhere)
            ->postJson('api/v1/portal/pharmacy/drugs', $this->drugBody(['nafdac_number' => 'A4-1234']))
            ->assertCreated();

        $theirs = $this->picture($elsewhere, MediaPurpose::DrugImage);

        $this->actingAs($this->account)
            ->postJson('api/v1/portal/pharmacy/drugs', $this->drugBody(['nafdac_number' => 'A4-9999', 'image_url' => $theirs]))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'MEDIA_NOT_FOUND')
            ->assertJsonPath('error.field', 'image_url');

        $this->actingAs($this->account)
            ->postJson('api/v1/portal/pharmacy/drugs', $this->drugBody(['nafdac_number' => 'not a number']))
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'nafdac_number');
    }

    public function test_another_pharmacys_medicine_is_not_found(): void
    {
        [, $elsewhere] = $this->approvedPartner();

        $theirs = $this->actingAs($elsewhere)
            ->postJson('api/v1/portal/pharmacy/drugs', $this->drugBody())
            ->json('data.id');

        $this->actingAs($this->account)
            ->getJson("api/v1/portal/pharmacy/drugs/{$theirs}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'DRUG_NOT_FOUND');

        $this->actingAs($this->account)
            ->deleteJson("api/v1/portal/pharmacy/drugs/{$theirs}")
            ->assertNotFound();
    }

    public function test_only_pharmacy_accounts_with_a_pharmacy_reach_the_portal(): void
    {
        [, $laboratory] = $this->approvedPartner(PartnerKind::Laboratory);

        $this->getJson('api/v1/portal/pharmacy')->assertUnauthorized();

        $this->actingAs($laboratory)
            ->getJson('api/v1/portal/pharmacy')
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->getJson('api/v1/portal/pharmacy/drugs')
            ->assertForbidden();

        $orphan = User::factory()
            ->portal(Role::Pharmacy)
            ->create();

        $this->actingAs($orphan)
            ->getJson('api/v1/portal/pharmacy/summary')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'PHARMACY_NOT_FOUND');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function settings(array $overrides = []): array
    {
        return [
            'name' => 'Medplus Lekki',
            'state' => 'Lagos',
            'city' => 'Lekki',
            'address' => '3 Admiralty Way',
            'logo_url' => null,
            'cover_url' => null,
            'delivery_fee_kobo' => 120000,
            'free_delivery_over_kobo' => 2000000,
            'delivery_minutes' => 45,
            ...$overrides,
        ];
    }
}
