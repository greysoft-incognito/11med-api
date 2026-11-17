<?php

namespace Tests\Feature\Laboratories;

use App\Enums\LabBookingStatus;
use App\Enums\MediaPurpose;
use App\Enums\PartnerKind;
use App\Enums\Role;
use App\Models\LabBooking;
use App\Models\Laboratory;
use App\Models\User;
use Tests\Concerns\MakesLaboratories;
use Tests\TestCase;

class PortalLaboratoryTest extends TestCase
{
    use MakesLaboratories;

    private function settings(array $overrides = []): array
    {
        return [
            'name' => 'Synlab Ikeja',
            'state' => 'Lagos',
            'city' => 'Ikeja',
            'address' => '3 Obafemi Awolowo Way',
            'logo_url' => null,
            'cover_url' => null,
            'opens' => '07:30',
            'closes' => '19:00',
            'home_collection_fee_kobo' => 250000,
            ...$overrides,
        ];
    }

    public function test_approval_opens_a_listing_with_default_hours(): void
    {
        [$laboratory, $account] = $this->laboratory();

        $this->assertSame(Laboratory::DEFAULT_OPENS, $laboratory->opens);

        $this->actingAs($account)
            ->getJson('api/v1/portal/laboratory')
            ->assertOk()
            ->assertJsonPath('data.id', $laboratory->organisation->uuid)
            ->assertJsonPath('data.name', $laboratory->organisation->name)
            ->assertJsonPath('data.opens', '08:00')
            ->assertJsonPath('data.closes', '17:00')
            ->assertJsonPath('data.home_collection_fee_kobo', null)
            ->assertJsonPath('data.tests_count', 0)
            ->assertJsonPath('data.rating', null);

        [$pharmacy] = $this->approvedPartner(PartnerKind::Pharmacy);

        $this->assertFalse(Laboratory::query()->where('organisation_id', $pharmacy->id)->exists());
    }

    public function test_the_laboratory_edits_its_settings_with_its_own_pictures(): void
    {
        [$laboratory, $account] = $this->laboratory();
        $logo = $this->picture($account, MediaPurpose::LaboratoryLogo);

        $this->actingAs($account)
            ->patchJson('api/v1/portal/laboratory', $this->settings(['logo_url' => $logo]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Synlab Ikeja')
            ->assertJsonPath('data.city', 'Ikeja')
            ->assertJsonPath('data.logo_url', $logo)
            ->assertJsonPath('data.cover_url', null)
            ->assertJsonPath('data.opens', '07:30')
            ->assertJsonPath('data.home_collection_fee_kobo', 250000);

        $this->assertSame('Synlab Ikeja', $laboratory->organisation->refresh()->name);

        $this->actingAs($account)
            ->patchJson('api/v1/portal/laboratory', $this->settings(['logo_url' => $logo]))
            ->assertOk()
            ->assertJsonPath('data.logo_url', $logo);

        $this->actingAs($account)
            ->patchJson('api/v1/portal/laboratory', $this->settings())
            ->assertOk()
            ->assertJsonPath('data.logo_url', null);

        $this->assertDatabaseMissing('media', ['uuid' => str($logo)->match('~[0-9a-f-]{36}~')->toString()]);
    }

    public function test_settings_refusals(): void
    {
        [, $account] = $this->laboratory();
        [, $other] = $this->laboratory();
        $theirs = $this->picture($other, MediaPurpose::LaboratoryCover);

        $this->actingAs($account)
            ->patchJson('api/v1/portal/laboratory', $this->settings(['closes' => '07:00']))
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.field', 'closes');

        $this->actingAs($account)
            ->patchJson('api/v1/portal/laboratory', $this->settings(['cover_url' => $theirs]))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'MEDIA_NOT_FOUND')
            ->assertJsonPath('error.field', 'cover_url');

        $this->actingAs($account)
            ->patchJson('api/v1/portal/laboratory', $this->settings(['state' => 'Atlantis']))
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'state');
    }

    public function test_an_account_without_a_laboratory_and_other_roles(): void
    {
        $this->getJson('api/v1/portal/laboratory/summary')->assertUnauthorized();

        $orphan = User::factory()->portal(Role::Laboratory)->create();
        [, $pharmacy] = $this->approvedPartner(PartnerKind::Pharmacy);

        $this->actingAs($orphan)
            ->getJson('api/v1/portal/laboratory')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'LAB_NOT_FOUND');

        $this->actingAs($pharmacy)->getJson('api/v1/portal/laboratory')->assertForbidden();
        $this->actingAs($this->admin())->getJson('api/v1/portal/laboratory/tests')->assertForbidden();
    }

    public function test_the_price_list_against_the_catalogue(): void
    {
        $this->labTest('FBC', ['name' => 'Full blood count']);
        $this->labTest('MP', ['name' => 'Malaria parasite', 'category' => 'microbiology']);
        [, $account] = $this->laboratory();

        $this->actingAs($account)
            ->putJson('api/v1/portal/laboratory/tests/FBC', ['price_kobo' => 450000, 'available' => true])
            ->assertCreated()
            ->assertJsonPath('data.code', 'FBC')
            ->assertJsonPath('data.price_kobo', 450000)
            ->assertJsonPath('data.available', true);

        $this->actingAs($account)
            ->putJson('api/v1/portal/laboratory/tests/FBC', ['price_kobo' => 500000, 'available' => false])
            ->assertOk()
            ->assertJsonPath('data.price_kobo', 500000)
            ->assertJsonPath('data.available', false);

        $this->actingAs($account)
            ->getJson('api/v1/portal/laboratory/catalogue')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'FBC')
            ->assertJsonPath('data.0.offered', ['price_kobo' => 500000, 'available' => false])
            ->assertJsonPath('data.1.code', 'MP')
            ->assertJsonPath('data.1.offered', null);

        $this->actingAs($account)
            ->getJson('api/v1/portal/laboratory/catalogue?category=microbiology')
            ->assertJsonCount(1, 'data');

        $this->actingAs($account)
            ->getJson('api/v1/portal/laboratory/tests')
            ->assertJsonCount(1, 'data');

        $this->actingAs($account)
            ->getJson('api/v1/portal/laboratory')
            ->assertJsonPath('data.tests_count', 0);

        $this->actingAs($account)
            ->putJson('api/v1/portal/laboratory/tests/NOPE', ['price_kobo' => 1, 'available' => true])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'UNKNOWN_TEST')
            ->assertJsonPath('error.field', 'test_code');

        $this->actingAs($account)
            ->putJson('api/v1/portal/laboratory/tests/MP', ['price_kobo' => -1, 'available' => true])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'price_kobo');

        $this->actingAs($account)
            ->deleteJson('api/v1/portal/laboratory/tests/MP')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'TEST_NOT_LISTED');

        $this->actingAs($account)
            ->deleteJson('api/v1/portal/laboratory/tests/FBC')
            ->assertOk();

        $this->actingAs($account)
            ->getJson('api/v1/portal/laboratory/tests')
            ->assertJsonCount(0, 'data');

        $this->actingAs($account)
            ->putJson('api/v1/portal/laboratory/tests/FBC', ['price_kobo' => 400000, 'available' => true])
            ->assertCreated();
    }

    public function test_a_test_in_an_open_booking_stays_on_the_list(): void
    {
        $this->fakeLabSeams();
        [$laboratory, $account] = $this->laboratory(['FBC' => 450000]);

        $this->actingAs($this->labPatient())
            ->postJson('api/v1/labs/bookings', $this->bookingBody($laboratory, ['FBC']))
            ->assertCreated();

        $this->actingAs($account)
            ->deleteJson('api/v1/portal/laboratory/tests/FBC')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'TEST_IN_OPEN_BOOKING');

        LabBooking::query()->update(['status' => LabBookingStatus::ResultsReady]);

        $this->actingAs($account)
            ->deleteJson('api/v1/portal/laboratory/tests/FBC')
            ->assertOk();
    }
}
