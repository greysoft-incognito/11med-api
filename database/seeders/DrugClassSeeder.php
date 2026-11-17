<?php

namespace Database\Seeders;

use App\Models\DrugClass;
use App\Models\DrugClassMember;
use Illuminate\Database\Seeder;

/**
 * The allergy cross-check's drug classes (app handover §1.1): generic names and the brands common in
 * Nigeria, lower case, one word or the whole name. Curated, not yet reviewed by a clinician (HT-009).
 * Safe to run again: classes are matched by code and members added once.
 */
class DrugClassSeeder extends Seeder
{
    public const CLASSES = [
        'beta_lactams' => ['Beta-lactams', [
            'beta lactam', 'penicillin', 'amoxicillin', 'ampicillin', 'amoxiclav', 'augmentin', 'ampiclox', 'cloxacillin',
            'flucloxacillin', 'piperacillin', 'benzylpenicillin', 'phenoxymethylpenicillin', 'cephalosporin', 'cefalexin',
            'cephalexin', 'cefuroxime', 'ceftriaxone', 'cefixime', 'cefpodoxime', 'ceftazidime', 'cefotaxime', 'cefazolin',
        ]],
        'penicillins' => ['Penicillins', [
            'penicillin', 'amoxicillin', 'ampicillin', 'amoxiclav', 'augmentin', 'ampiclox', 'cloxacillin', 'flucloxacillin',
            'piperacillin', 'benzylpenicillin', 'phenoxymethylpenicillin',
        ]],
        'cephalosporins' => ['Cephalosporins', [
            'cephalosporin', 'cefalexin', 'cephalexin', 'cefuroxime', 'ceftriaxone', 'cefixime', 'cefpodoxime', 'ceftazidime',
            'cefotaxime', 'cefazolin',
        ]],
        'sulfonamides' => ['Sulfonamides', [
            'sulfonamide', 'sulfa', 'sulphonamide', 'sulfamethoxazole', 'cotrimoxazole', 'co trimoxazole', 'septrin',
            'sulfadiazine', 'sulfadoxine', 'fansidar',
        ]],
        'nsaids' => ['NSAIDs', [
            'nsaid', 'ibuprofen', 'diclofenac', 'naproxen', 'aspirin', 'piroxicam', 'indomethacin', 'ketoprofen',
            'mefenamic acid', 'ponstan', 'celecoxib', 'meloxicam',
        ]],
        'macrolides' => ['Macrolides', ['macrolide', 'erythromycin', 'azithromycin', 'clarithromycin']],
        'fluoroquinolones' => ['Fluoroquinolones', [
            'quinolone', 'fluoroquinolone', 'ciprofloxacin', 'levofloxacin', 'ofloxacin', 'moxifloxacin', 'norfloxacin',
        ]],
        'tetracyclines' => ['Tetracyclines', ['tetracycline', 'doxycycline', 'minocycline']],
        'opioids' => ['Opioids', ['opioid', 'morphine', 'codeine', 'tramadol', 'pethidine', 'pentazocine']],
        'artemisinins' => ['Artemisinins', [
            'artemisinin', 'artemether', 'artesunate', 'dihydroartemisinin', 'coartem', 'lonart',
        ]],
        'ace_inhibitors' => ['ACE inhibitors', ['ace inhibitor', 'lisinopril', 'enalapril', 'captopril', 'ramipril']],
    ];

    public function run(): void
    {
        foreach (self::CLASSES as $code => [$name, $members]) {
            $class = DrugClass::query()->firstOrCreate(['code' => $code], ['name' => $name]);

            foreach ($members as $member) {
                DrugClassMember::query()->firstOrCreate(['drug_class_id' => $class->id, 'name' => $member]);
            }
        }
    }
}
