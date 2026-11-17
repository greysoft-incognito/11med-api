<?php

namespace App\Services\Guide;

/**
 * Tests asked for by name (the app mock's rules).
 */
final class TestRules
{
    private const INTENT = ['\btests?\b', '\blab\b', 'laborator', 'check my', 'screen(ing)?', 'blood work', '\bresults?\b', 'scan'];

    private const WORDS = [
        [['sugar', 'glucose', 'diabet'], 'HBA1C', 'Find an HbA1c test'],
        [['fasting'], 'FBS', 'Find a fasting blood sugar test'],
        [['malaria'], 'MP', 'Find a malaria test'],
        [['typhoid', 'widal'], 'WIDAL', 'Find a typhoid test'],
        [['pregnan'], 'PT', 'Find a pregnancy test'],
        [['\bhiv\b'], 'HIV', 'Find HIV screening'],
        [['hepatitis'], 'HBSAG', 'Find a hepatitis B test'],
        [['genotype', 'sickle'], 'GENOTYPE', 'Find a genotype test'],
        [['blood group', 'blood type'], 'BLOODGROUP', 'Find a blood group test'],
        [['kidney', 'creatinine'], 'EUCR', 'Find a kidney function test'],
        [['liver'], 'LFT', 'Find a liver function test'],
        [['cholesterol', 'lipid'], 'LIPID', 'Find a lipid profile'],
        [['thyroid'], 'TSH', 'Find a thyroid test'],
        [['prostate', '\bpsa\b'], 'PSA', 'Find a PSA test'],
        [['urine', '\bpee\b'], 'URINALYSIS', 'Find a urine test'],
        [['blood count', '\bfbc\b', 'anaemi', 'anemi', 'full blood'], 'FBC', 'Find a full blood count'],
    ];

    public function asked(string $text): bool
    {
        return Patterns::any(self::INTENT, $text);
    }

    /**
     * @return array{0: string, 1: string}|null the code and the action's label
     */
    public function named(string $text): ?array
    {
        foreach (self::WORDS as [$patterns, $code, $label]) {
            if (Patterns::any($patterns, $text)) {
                return [$code, $label];
            }
        }

        return null;
    }
}
