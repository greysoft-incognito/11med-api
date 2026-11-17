<?php

namespace Tests\Concerns;

use App\Models\Drug;
use App\Models\Organisation;

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
}
