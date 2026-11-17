<?php

namespace Database\Seeders;

use App\Models\LabTest;
use Illuminate\Database\Seeder;

/**
 * The starting test catalogue: the tests a Nigerian general practice asks for most, as the app's
 * mock seeds them. Adds a code only when it is missing, so the team's edits are never undone.
 * Run once per server: `php artisan db:seed --class=LabCatalogueSeeder --force`.
 */
class LabCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $tests = [
            ['FBC', 'Full blood count', 'haematology', 'blood', 'Counts your red cells, white cells and platelets. The first test for tiredness, infection or anaemia.', 0, 24],
            ['ESR', 'ESR', 'haematology', 'blood', 'How quickly red cells settle, which rises with inflammation anywhere in the body.', 0, 24],
            ['GENOTYPE', 'Genotype', 'haematology', 'blood', 'Whether you carry the sickle cell trait: AA, AS, SS, AC or SC.', 0, 48],
            ['BLOODGROUP', 'Blood group', 'haematology', 'blood', 'Your ABO group and rhesus factor, for transfusion and pregnancy.', 0, 24],
            ['MP', 'Malaria parasite', 'microbiology', 'blood', 'Looks for the malaria parasite in your blood. Done the same day.', 0, 4],
            ['WIDAL', 'Widal test', 'serology', 'blood', 'Screens for typhoid. A positive result usually needs a culture to confirm.', 0, 24],
            ['FBS', 'Fasting blood sugar', 'chemistry', 'blood', 'Your blood sugar after an overnight fast. Screens for diabetes.', 8, 4],
            ['HBA1C', 'HbA1c', 'chemistry', 'blood', 'Your average blood sugar over the last three months. How diabetes control is judged.', 0, 48],
            ['LIPID', 'Lipid profile', 'chemistry', 'blood', 'Cholesterol and triglycerides, which the heart risk assessment is built on.', 10, 24],
            ['EUCR', 'Kidney function (E/U/Cr)', 'chemistry', 'blood', 'Electrolytes, urea and creatinine: how well your kidneys are filtering.', 0, 24],
            ['LFT', 'Liver function test', 'chemistry', 'blood', 'Enzymes and proteins that show how your liver is coping.', 0, 24],
            ['URICACID', 'Uric acid', 'chemistry', 'blood', 'Raised in gout and some kidney conditions.', 0, 24],
            ['TSH', 'Thyroid (TSH)', 'hormones', 'blood', 'The first thyroid test, for an overactive or underactive gland.', 0, 48],
            ['PT', 'Pregnancy test', 'hormones', 'urine', 'Confirms a pregnancy from a urine sample.', 0, 2],
            ['HIV', 'HIV screening', 'serology', 'blood', 'Confidential screening, with counselling before and after.', 0, 4],
            ['HBSAG', 'Hepatitis B', 'serology', 'blood', 'Screens for hepatitis B, which is common and often silent.', 0, 24],
            ['URINALYSIS', 'Urinalysis', 'urinalysis', 'urine', 'Checks urine for sugar, protein, blood and infection.', 0, 4],
            ['URINEMCS', 'Urine culture (MCS)', 'microbiology', 'urine', 'Grows whatever is causing a urinary infection and finds the antibiotic that works on it.', 0, 72],
            ['STOOLMCS', 'Stool culture (MCS)', 'microbiology', 'stool', 'For diarrhoea that will not settle: identifies the bacteria or parasite.', 0, 72],
            ['PSA', 'PSA', 'hormones', 'blood', 'Prostate screening for men over 40, or with urinary symptoms.', 0, 48],
        ];

        foreach ($tests as [$code, $name, $category, $sample, $description, $fasting, $turnaround]) {
            LabTest::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'category' => $category,
                'sample' => $sample,
                'description' => $description,
                'fasting_hours' => $fasting,
                'turnaround_hours' => $turnaround,
            ]);
        }
    }
}
