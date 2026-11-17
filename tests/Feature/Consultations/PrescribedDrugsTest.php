<?php

namespace Tests\Feature\Consultations;

use App\Enums\PartnerKind;
use App\Models\Drug;
use Database\Seeders\DrugClassSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesPartners;
use Tests\TestCase;

class PrescribedDrugsTest extends TestCase
{
    use MakesConsultations;
    use MakesPartners;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Notification::fake();
        $this->fakeSeams();
        $this->seed(DrugClassSeeder::class);
    }

    public function test_a_prescription_unblocks_its_medicine_in_the_cart(): void
    {
        [$pharmacy] = $this->approvedPartner(PartnerKind::Pharmacy);

        $amox = Drug::create([
            'organisation_id' => $pharmacy->id,
            'name' => 'Amoxicillin',
            'nafdac_number' => 'A4-1234',
            'category' => 'antibiotics',
            'dosage_form' => 'capsule',
            'dosage_strength' => '500mg',
            'price_kobo' => 200000,
            'requires_prescription' => true,
            'in_stock' => true,
        ]);

        $patient = $this->patient();
        $doctor = $this->availableDoctor();

        $this->actingAs($patient)
            ->patchJson('api/v1/pharmacy/cart', ['drug_id' => $amox->uuid, 'quantity' => 1])
            ->assertOk()
            ->assertJsonPath('data.blocked_items', [$amox->uuid]);

        $id = $this->openSession($patient, $doctor);

        $this->actingAs($doctor)
            ->postJson("api/v1/doctor/consultations/{$id}/prescription", [
                'diagnosis_summary' => 'Bacterial tonsillitis',
                'drugs' => [[
                    'drug_name' => ' AMOXICILLIN ',
                    'dosage_strength' => '500mg',
                    'dosage_form' => 'capsule',
                    'dose_quantity' => 1,
                    'dose_unit' => null,
                    'times' => ['08:00', '20:00'],
                    'duration_days' => 7,
                    'special_instructions' => null,
                ]],
            ])
            ->assertCreated();

        $this->actingAs($patient)
            ->getJson('api/v1/pharmacy/cart')
            ->assertOk()
            ->assertJsonPath('data.blocked_items', []);
    }
}
