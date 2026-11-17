<?php

namespace Database\Seeders\Demo;

use App\Enums\MediaPurpose;
use App\Enums\PartnerKind;
use App\Models\Drug;
use App\Models\Pharmacy;

/**
 * The app mock's five pharmacies and their shelves (`seedCatalogue.ts`): the same drug at
 * two prices, one line out of stock, every dosage form at least once.
 */
class DemoPharmacies
{
    private const PRODUCT_PHOTOS = [
        'tablet' => ['1584308666744-24d5c474f2ae', '1550572017-edd951b55104', '1631549916768-4119b2e5f926', '1603398938378-e54eab446dde'],
        'capsule' => ['1587854692152-cbe660dbde88', '1584362917165-526a968579e8', '1471864190281-a93a3070b6de'],
        'syrup' => ['1576671081837-49000212a370', '1586015555751-63bb77f4322a'],
        'drops' => ['1586015555751-63bb77f4322a', '1576671081837-49000212a370'],
        'other' => ['1628771065518-0d82f1938462', '1585435557343-3b092031a831', '1607619056574-7b8d3ee536b2', '1631217868264-e5b90bb7e133'],
    ];

    private int $nth = 0;

    public function __construct(
        private readonly DemoPictures $pictures,
    ) {}

    public function create(): void
    {
        foreach (self::pharmacies() as $i => $pharmacy) {
            [$organisation, $account] = DemoPartners::approve(PartnerKind::Pharmacy, $pharmacy, $i + 1);

            $store = Pharmacy::query()
                ->where('organisation_id', $organisation->id)
                ->firstOrFail();
            $store->fill([
                'delivery_fee_kobo' => $pharmacy['delivery_fee_kobo'],
                'free_delivery_over_kobo' => $pharmacy['free_delivery_over_kobo'],
                'delivery_minutes' => $pharmacy['delivery_minutes'],
                'cover_media_id' => $this->pictures->store($account, MediaPurpose::PharmacyCover, DemoPictures::unsplash($pharmacy['cover'], 800, 480))?->id,
            ]);
            $store->forceFill(['rating' => $pharmacy['rating'][0] ?? null, 'rating_count' => $pharmacy['rating'][1] ?? 0]);
            $store->save();

            foreach ($pharmacy['drugs'] as $drug) {
                $photos = self::PRODUCT_PHOTOS[$drug['dosage_form']] ?? self::PRODUCT_PHOTOS['other'];
                $photo = $photos[++$this->nth % count($photos)];

                Drug::create([
                    'organisation_id' => $organisation->id,
                    'brand_name' => null,
                    'in_stock' => true,
                    ...$drug,
                    'image_media_id' => $this->pictures->store($account, MediaPurpose::DrugImage, DemoPictures::unsplash($photo, 480, 480))?->id,
                ]);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function pharmacies(): array
    {
        $amoxicillin = ['name' => 'Amoxicillin', 'nafdac_number' => 'A4-0821', 'category' => 'antibiotics', 'dosage_form' => 'capsule', 'dosage_strength' => '500mg', 'description' => 'Broad spectrum antibiotic. Finish the full course even if you feel better.', 'price_kobo' => 180_000, 'requires_prescription' => true];
        $paracetamol = ['name' => 'Paracetamol', 'brand_name' => 'Panadol', 'nafdac_number' => 'A4-1190', 'category' => 'analgesics', 'dosage_form' => 'tablet', 'dosage_strength' => '500mg', 'description' => 'For pain and fever.', 'price_kobo' => 35_000, 'requires_prescription' => false];
        $coartem = ['name' => 'Artemether/Lumefantrine', 'brand_name' => 'Coartem', 'nafdac_number' => 'A4-3302', 'category' => 'antimalarials', 'dosage_form' => 'tablet', 'dosage_strength' => '20mg/120mg', 'description' => 'Treatment for uncomplicated malaria. Take with food.', 'price_kobo' => 320_000, 'requires_prescription' => false];
        $metformin = ['name' => 'Metformin', 'brand_name' => 'Glucophage', 'nafdac_number' => 'A4-0455', 'category' => 'antidiabetics', 'dosage_form' => 'tablet', 'dosage_strength' => '500mg', 'description' => 'For type 2 diabetes. Take with a meal to reduce stomach upset.', 'price_kobo' => 240_000, 'requires_prescription' => true];
        $vitaminC = ['name' => 'Vitamin C', 'nafdac_number' => 'A4-9014', 'category' => 'vitamins', 'dosage_form' => 'tablet', 'dosage_strength' => '1000mg', 'description' => 'Supplement.', 'price_kobo' => 90_000, 'requires_prescription' => false];

        return [
            ['name' => 'Harbourview Pharmacy', 'state' => 'Lagos', 'city' => 'Lekki', 'address' => '12 Admiralty Way, Lekki Phase 1', 'cover' => '1576602976047-174e57a47881', 'rating' => [4.7, 312], 'delivery_fee_kobo' => 150_000, 'free_delivery_over_kobo' => 1_000_000, 'delivery_minutes' => 90, 'drugs' => [
                $amoxicillin, $paracetamol, $coartem,
                ['name' => 'Cetirizine', 'nafdac_number' => 'A4-6620', 'category' => 'antihistamines', 'dosage_form' => 'tablet', 'dosage_strength' => '10mg', 'description' => 'For allergies. May cause drowsiness.', 'price_kobo' => 75_000, 'requires_prescription' => false],
            ]],
            ['name' => 'Greenfield Pharmacy', 'state' => 'Lagos', 'city' => 'Yaba', 'address' => '23 Herbert Macaulay Way, Yaba', 'cover' => '1583947215259-38e31be8751f', 'rating' => [4.8, 97], 'delivery_fee_kobo' => 100_000, 'free_delivery_over_kobo' => 800_000, 'delivery_minutes' => 60, 'drugs' => [
                [...$paracetamol, 'brand_name' => null, 'nafdac_number' => 'A4-1204', 'price_kobo' => 30_000],
                ['name' => 'Ibuprofen', 'nafdac_number' => 'A4-2210', 'category' => 'analgesics', 'dosage_form' => 'tablet', 'dosage_strength' => '400mg', 'description' => 'For pain and inflammation. Take after food.', 'price_kobo' => 60_000, 'requires_prescription' => false],
                ['name' => 'Loratadine', 'nafdac_number' => 'A4-6711', 'category' => 'antihistamines', 'dosage_form' => 'tablet', 'dosage_strength' => '10mg', 'description' => 'For allergies. Less drowsy than older antihistamines.', 'price_kobo' => 65_000, 'requires_prescription' => false],
                [...$metformin, 'brand_name' => null, 'nafdac_number' => 'A4-0471', 'price_kobo' => 230_000],
                ['name' => 'Paracetamol', 'nafdac_number' => 'A4-1377', 'category' => 'analgesics', 'dosage_form' => 'syrup', 'dosage_strength' => '120mg/5ml', 'description' => 'For children. Dose by weight, with the measure supplied.', 'price_kobo' => 120_000, 'requires_prescription' => false],
            ]],
            ['name' => 'Capital Pharmacy', 'state' => 'FCT', 'city' => 'Wuse', 'address' => 'Plot 64 Aminu Kano Crescent, Wuse 2', 'cover' => '1586015555751-63bb77f4322a', 'rating' => [4.5, 188], 'delivery_fee_kobo' => 200_000, 'free_delivery_over_kobo' => 1_500_000, 'delivery_minutes' => 120, 'drugs' => [
                ['name' => 'Amlodipine', 'nafdac_number' => 'A4-2277', 'category' => 'antihypertensives', 'dosage_form' => 'tablet', 'dosage_strength' => '10mg', 'description' => 'Lowers blood pressure. Taken once daily, long term.', 'price_kobo' => 210_000, 'requires_prescription' => true],
                [...$metformin, 'in_stock' => false],
                $vitaminC,
                ['name' => 'Salbutamol', 'nafdac_number' => 'A4-5508', 'category' => 'other', 'dosage_form' => 'inhaler', 'dosage_strength' => '100mcg', 'description' => 'Reliever inhaler for asthma. Carry it with you.', 'price_kobo' => 250_000, 'requires_prescription' => true],
            ]],
            ['name' => 'Cedar Pharmacy', 'state' => 'Oyo', 'city' => 'Ibadan', 'address' => '5 Bodija Market Road, Ibadan', 'cover' => '1631217868264-e5b90bb7e133', 'rating' => null, 'delivery_fee_kobo' => 120_000, 'free_delivery_over_kobo' => null, 'delivery_minutes' => 90, 'drugs' => [
                [...$coartem, 'nafdac_number' => 'A4-3318', 'price_kobo' => 300_000],
                ['name' => 'Lisinopril', 'nafdac_number' => 'A4-2390', 'category' => 'antihypertensives', 'dosage_form' => 'tablet', 'dosage_strength' => '10mg', 'description' => 'Lowers blood pressure. May cause a dry cough; tell your doctor if it does.', 'price_kobo' => 190_000, 'requires_prescription' => true],
                ['name' => 'Hydrocortisone', 'nafdac_number' => 'A4-7740', 'category' => 'other', 'dosage_form' => 'cream', 'dosage_strength' => '1%', 'description' => 'For itching and mild skin inflammation. Thinly, twice a day.', 'price_kobo' => 110_000, 'requires_prescription' => false],
            ]],
            ['name' => 'Riverside Pharmacy', 'state' => 'Rivers', 'city' => 'Port Harcourt', 'address' => '88 Aba Road, Port Harcourt', 'cover' => '1607619056574-7b8d3ee536b2', 'rating' => [4.2, 41], 'delivery_fee_kobo' => 250_000, 'free_delivery_over_kobo' => 2_000_000, 'delivery_minutes' => 150, 'drugs' => [
                [...$amoxicillin, 'nafdac_number' => 'A4-0836', 'price_kobo' => 200_000],
                [...$vitaminC, 'nafdac_number' => 'A4-9020', 'price_kobo' => 95_000],
                ['name' => 'Chloramphenicol', 'nafdac_number' => 'A4-8102', 'category' => 'antibiotics', 'dosage_form' => 'drops', 'dosage_strength' => '0.5%', 'description' => 'Eye drops for bacterial conjunctivitis. Discard four weeks after opening.', 'price_kobo' => 90_000, 'requires_prescription' => true],
            ]],
        ];
    }
}
