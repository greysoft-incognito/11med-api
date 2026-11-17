<?php

namespace Database\Seeders\Demo;

use App\Enums\MediaPurpose;
use App\Enums\PartnerKind;
use App\Models\Laboratory;
use App\Models\LaboratoryTest;
use App\Models\LabTest;

/**
 * The app mock's five laboratories and their price lists (`seedLabs.ts`): prices differ, every
 * list misses something, one offers no home collection, one lists a test it cannot run now.
 */
class DemoLaboratories
{
    private const BASE_PRICE_KOBO = [
        'FBC' => 500_000, 'ESR' => 250_000, 'GENOTYPE' => 300_000, 'BLOODGROUP' => 250_000, 'MP' => 200_000,
        'WIDAL' => 300_000, 'FBS' => 200_000, 'HBA1C' => 800_000, 'LIPID' => 900_000, 'EUCR' => 850_000,
        'LFT' => 900_000, 'URICACID' => 350_000, 'TSH' => 700_000, 'PT' => 150_000, 'HIV' => 300_000,
        'HBSAG' => 350_000, 'URINALYSIS' => 200_000, 'URINEMCS' => 600_000, 'STOOLMCS' => 600_000, 'PSA' => 900_000,
    ];

    public function __construct(
        private readonly DemoPictures $pictures,
    ) {}

    public function create(): void
    {
        $tests = LabTest::query()
            ->whereIn('code', array_keys(self::BASE_PRICE_KOBO))
            ->get();

        foreach (self::laboratories() as $i => $lab) {
            [$organisation, $account] = DemoPartners::approve(PartnerKind::Laboratory, $lab, $i + 1);

            $laboratory = Laboratory::query()
                ->where('organisation_id', $organisation->id)
                ->firstOrFail();
            $laboratory->fill([
                'opens' => $lab['opens'],
                'closes' => $lab['closes'],
                'home_collection_fee_kobo' => $lab['home_collection_fee_kobo'],
                'rating' => $lab['rating'][0] ?? null,
                'rating_count' => $lab['rating'][1] ?? 0,
                'cover_media_id' => $this->pictures->store($account, MediaPurpose::LaboratoryCover, DemoPictures::unsplash($lab['cover'], 800, 480))?->id,
            ]);
            $laboratory->save();

            foreach ($tests as $test) {
                if (in_array($test->code, $lab['omit'], true)) {
                    continue;
                }

                LaboratoryTest::create([
                    'laboratory_id' => $laboratory->id,
                    'lab_test_id' => $test->id,
                    'price_kobo' => (int) round(self::BASE_PRICE_KOBO[$test->code] * $lab['factor'] / 5_000) * 5_000,
                    'available' => ! in_array($test->code, $lab['unavailable'], true),
                ]);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function laboratories(): array
    {
        return [
            ['name' => 'Clinix Diagnostics', 'state' => 'Lagos', 'city' => 'Ikeja', 'address' => '14 Allen Avenue, Ikeja', 'cover' => '1532187863486-abf9dbad1b69', 'rating' => [4.7, 412], 'opens' => '07:00', 'closes' => '19:00', 'home_collection_fee_kobo' => 300_000, 'factor' => 1.1, 'omit' => [], 'unavailable' => []],
            ['name' => 'Lagoon Medical Laboratory', 'state' => 'Lagos', 'city' => 'Lekki', 'address' => 'Block 4, Admiralty Way, Lekki Phase 1', 'cover' => '1532094349884-543bc11b234d', 'rating' => [4.5, 188], 'opens' => '08:00', 'closes' => '18:00', 'home_collection_fee_kobo' => 500_000, 'factor' => 1.3, 'omit' => ['STOOLMCS', 'PSA'], 'unavailable' => ['TSH']],
            ['name' => 'Garki Path Lab', 'state' => 'FCT', 'city' => 'Garki', 'address' => 'Plot 112, Ahmadu Bello Way, Garki', 'cover' => '1518152006812-edab29b069ac', 'rating' => [4.6, 97], 'opens' => '08:00', 'closes' => '17:00', 'home_collection_fee_kobo' => null, 'factor' => 1.0, 'omit' => ['HBA1C', 'LIPID', 'URINEMCS'], 'unavailable' => []],
            ['name' => 'Rivers Reference Laboratory', 'state' => 'Rivers', 'city' => 'Port Harcourt', 'address' => '27 Aba Road, Port Harcourt', 'cover' => '1579165466991-467135ad3110', 'rating' => [4.2, 54], 'opens' => '07:30', 'closes' => '18:00', 'home_collection_fee_kobo' => 350_000, 'factor' => 0.9, 'omit' => ['TSH', 'PSA', 'HIV', 'GENOTYPE'], 'unavailable' => []],
            ['name' => 'Dala Medical Diagnostics', 'state' => 'Kano', 'city' => 'Kano', 'address' => '5 Zoo Road, Kano', 'cover' => '1576671081837-49000212a370', 'rating' => null, 'opens' => '08:00', 'closes' => '16:00', 'home_collection_fee_kobo' => 250_000, 'factor' => 0.8, 'omit' => ['HBA1C', 'LIPID', 'EUCR', 'LFT', 'TSH', 'PSA', 'STOOLMCS'], 'unavailable' => ['URINEMCS']],
        ];
    }
}
