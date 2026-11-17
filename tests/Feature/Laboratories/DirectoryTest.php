<?php

namespace Tests\Feature\Laboratories;

use App\Enums\PartnerKind;
use App\Enums\Role;
use App\Models\User;
use Tests\Concerns\MakesLaboratories;
use Tests\TestCase;

class DirectoryTest extends TestCase
{
    use MakesLaboratories;

    public function test_the_directory_ranks_the_patients_state_first_then_rating(): void
    {
        [$kano] = $this->laboratory(['FBC' => 300000], ['name' => 'Kano Lab', 'state' => 'Kano', 'city' => 'Kano'], ['rating' => 4.9, 'rating_count' => 10]);
        [$unrated] = $this->laboratory(['FBC' => 400000], ['name' => 'Ajah Lab', 'state' => 'Lagos', 'city' => 'Ajah']);
        [$ikeja] = $this->laboratory(['FBC' => [350000, false], 'MP' => 200000], ['name' => 'Ikeja Lab', 'state' => 'Lagos', 'city' => 'Ikeja'], ['rating' => 4.2, 'rating_count' => 3]);
        $patient = $this->labPatient('Lagos');

        $this->actingAs($patient)
            ->getJson('api/v1/labs')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.items.0.id', $ikeja->organisation->uuid)
            ->assertJsonPath('data.items.0.rating', 4.2)
            ->assertJsonPath('data.items.0.tests_count', 1)
            ->assertJsonPath('data.items.0.test_price_kobo', null)
            ->assertJsonPath('data.items.1.id', $unrated->organisation->uuid)
            ->assertJsonPath('data.items.2.id', $kano->organisation->uuid);

        $this->actingAs($this->labPatient())
            ->getJson('api/v1/labs')
            ->assertJsonPath('data.items.0.id', $kano->organisation->uuid);

        $first = $this->actingAs($patient)
            ->getJson('api/v1/labs?limit=2')
            ->assertJsonCount(2, 'data.items')
            ->json('data.next_cursor');

        $this->actingAs($patient)
            ->getJson('api/v1/labs?limit=2&cursor=' . urlencode($first))
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $kano->organisation->uuid)
            ->assertJsonPath('data.next_cursor', null);

        $this->actingAs($patient)
            ->getJson('api/v1/labs?q=ikeja')
            ->assertJsonPath('data.total', 1);

        $this->actingAs($patient)
            ->getJson('api/v1/labs?test=fbc')
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.0.id', $unrated->organisation->uuid)
            ->assertJsonPath('data.items.0.test_price_kobo', 400000)
            ->assertJsonPath('data.items.1.test_price_kobo', 300000);
    }

    public function test_the_catalogue_gives_the_cheapest_runnable_price(): void
    {
        $this->labTest('LFT', ['name' => 'Liver function', 'category' => 'chemistry']);
        $this->laboratory(['FBC' => 300000, 'MP' => [100000, false]]);
        $this->laboratory(['FBC' => 250000, 'MP' => 150000]);
        [$suspended] = $this->laboratory(['FBC' => 1000]);
        $this->suspend($suspended->organisation);

        $this->actingAs($this->labPatient())
            ->getJson('api/v1/labs/tests')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.code', 'LFT')
            ->assertJsonPath('data.0.from_kobo', null)
            ->assertJsonPath('data.0.offered_by', 0)
            ->assertJsonPath('data.1.code', 'FBC')
            ->assertJsonPath('data.1.from_kobo', 250000)
            ->assertJsonPath('data.1.offered_by', 2)
            ->assertJsonPath('data.2.code', 'MP')
            ->assertJsonPath('data.2.from_kobo', 150000)
            ->assertJsonPath('data.2.offered_by', 1);

        $doctor = User::factory()->app(Role::Doctor)->create();

        $this->actingAs($doctor)
            ->getJson('api/v1/labs/tests?category=chemistry')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_one_laboratory_and_its_price_list(): void
    {
        [$laboratory] = $this->laboratory(['FBC' => 300000, 'MP' => [100000, false]], [], ['home_collection_fee_kobo' => 200000]);
        $patient = $this->labPatient();

        $this->actingAs($patient)
            ->getJson('api/v1/labs/' . $laboratory->organisation->uuid)
            ->assertOk()
            ->assertJsonPath('data.home_collection_fee_kobo', 200000)
            ->assertJsonPath('data.tests_count', 1);

        $this->actingAs($patient)
            ->getJson('api/v1/labs/' . $laboratory->organisation->uuid . '/tests')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.code', 'MP')
            ->assertJsonPath('data.1.available', false);

        $this->actingAs($patient)
            ->getJson('api/v1/labs/' . $laboratory->organisation->uuid . '/tests?q=mp')
            ->assertJsonCount(1, 'data');
    }

    public function test_an_unlisted_laboratory_is_not_found(): void
    {
        [$laboratory] = $this->laboratory();
        [$pharmacy] = $this->approvedPartner(PartnerKind::Pharmacy);
        $this->suspend($laboratory->organisation);
        $patient = $this->labPatient();

        foreach ([$laboratory->organisation->uuid, $pharmacy->uuid, fake()->uuid()] as $id) {
            $this->actingAs($patient)
                ->getJson("api/v1/labs/{$id}")
                ->assertNotFound()
                ->assertJsonPath('error.code', 'LAB_NOT_FOUND');
        }

        $this->actingAs($patient)
            ->getJson('api/v1/labs')
            ->assertJsonPath('data.total', 0);
    }

    public function test_portal_accounts_do_not_browse_the_directory(): void
    {
        [, $account] = $this->laboratory();

        $this->getJson('api/v1/labs')->assertUnauthorized();

        $this->actingAs($account)->getJson('api/v1/labs')->assertForbidden();
        $this->actingAs($account)->getJson('api/v1/labs/tests')->assertForbidden();
    }
}
