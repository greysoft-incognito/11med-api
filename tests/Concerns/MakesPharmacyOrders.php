<?php

namespace Tests\Concerns;

use App\Enums\Role;
use App\Models\Drug;
use App\Models\MedicalProfile;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Payments\Payments;
use Tests\Fakes\FakePayments;

/**
 * Medicines for the pharmacy feature tests.
 */
trait MakesPharmacyOrders
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function drugBody(array $overrides = []): array
    {
        return [
            'name' => 'Amoxicillin',
            'brand_name' => 'Amoxil',
            'nafdac_number' => 'A4-' . fake()->unique()->numerify('####'),
            'category' => 'antibiotics',
            'dosage_form' => 'capsule',
            'dosage_strength' => '500mg',
            'description' => 'For bacterial infections.',
            'price_kobo' => 200000,
            'requires_prescription' => false,
            'in_stock' => true,
            'image_url' => null,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function drug(Organisation $pharmacy, array $overrides = []): Drug
    {
        $body = $this->drugBody($overrides);
        unset($body['image_url']);

        return Drug::create(['organisation_id' => $pharmacy->id, ...$body]);
    }

    /**
     * A patient, with a profile in `$state` when one is named.
     */
    protected function patient(?string $state = null): User
    {
        $patient = User::factory()
            ->app(Role::Patient)
            ->create();

        if ($state !== null) {
            MedicalProfile::create([
                'user_id' => $patient->id,
                'date_of_birth' => '1980-01-01',
                'gender' => 'female',
                'blood_type' => 'O+',
                'genotype' => 'AA',
                'state_of_residence' => $state,
                'drug_allergies' => [],
                'contact_name' => 'Ngozi Obi',
                'contact_phone' => '08030000000',
                'contact_relationship' => 'sibling',
                'existing_conditions' => [],
                'past_surgeries' => [],
                'food_allergies' => [],
            ]);
        }

        return $patient;
    }

    protected function fakePayments(): FakePayments
    {
        $fake = new FakePayments;

        $this->app->instance(Payments::class, $fake);

        return $fake;
    }

    protected function addToCart(User $patient, Drug $drug, int $quantity = 1): void
    {
        $this->actingAs($patient)
            ->patchJson('api/v1/pharmacy/cart', ['drug_id' => $drug->uuid, 'quantity' => $quantity])
            ->assertOk();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<array<string, mixed>>
     */
    protected function checkout(User $patient, array $body = ['fulfilment' => 'pickup']): array
    {
        return $this->actingAs($patient)
            ->postJson('api/v1/pharmacy/orders', $body)
            ->assertCreated()
            ->json('data');
    }

    /**
     * @return array<string, string|null>
     */
    protected function address(): array
    {
        return ['line1' => '4 Bode Thomas Street', 'line2' => null, 'city' => 'Surulere', 'state' => 'Lagos', 'directions' => 'Blue gate'];
    }
}
