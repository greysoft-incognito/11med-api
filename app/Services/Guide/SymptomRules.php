<?php

namespace App\Services\Guide;

/**
 * Symptoms to a specialty, and the test a complaint usually starts with (the app mock's rules).
 */
final class SymptomRules
{
    /**
     * @var list<array{specialty: string, patterns: list<string>, says: string, test?: array{0: string, 1: string}, condition?: string}>
     */
    private const RULES = [
        [
            'specialty' => 'obstetrics',
            'patterns' => ['pregnan', 'missed (my )?period', 'antenatal', 'expecting', 'trimester', 'morning sickness'],
            'says' => 'That is one for an obstetrician, who looks after pregnancy from the first weeks.',
            'test' => ['PT', 'Find a pregnancy test'],
        ],
        [
            'specialty' => 'paediatrics',
            'patterns' => ['my (child|baby|son|daughter|toddler|kid)', '\b(baby|toddler|infant|newborn)\b', 'months? old', 'years? old (boy|girl|child)'],
            'says' => 'For a child, a paediatrician is the right doctor. They see exactly this every day.',
        ],
        [
            'specialty' => 'psychiatry',
            'patterns' => ['anxi', 'depress', 'panic', "can'?t sleep", 'insomnia', 'hopeless', 'no energy to', 'mood', 'stress', 'worr(y|ied) all the time'],
            'says' => 'A psychiatrist can help with this, and a chat consultation is a private way to start.',
        ],
        [
            'specialty' => 'cardiology',
            'patterns' => ['blood pressure', '\bbp\b', 'hypertens', 'palpitat', 'heart (is )?racing', 'heart beat', 'swollen (ankles|feet|legs)', 'breathless (when|on) (walking|climbing|lying)'],
            'says' => 'A cardiologist is the right doctor for this.',
            'test' => ['LIPID', 'Find a lipid profile'],
            'condition' => 'hypertens|blood pressure|heart',
        ],
        [
            'specialty' => 'endocrinology',
            'patterns' => ['sugar', 'diabet', 'always thirsty', 'urinat(e|ing) (a lot|often|all the time)', 'peeing (a lot|often)', 'losing weight without', 'thyroid', 'insulin'],
            'says' => 'An endocrinologist handles diabetes, thyroid and hormone problems.',
            'test' => ['HBA1C', 'Find an HbA1c test'],
            'condition' => 'diabet|sugar|thyroid',
        ],
        [
            'specialty' => 'nephrology',
            'patterns' => ['kidney', 'foamy urine', 'blood in (my )?urine', 'dialysis', 'swelling (around|under) (my )?eyes'],
            'says' => 'Kidney problems go to a nephrologist.',
            'test' => ['EUCR', 'Find a kidney function test'],
            'condition' => 'kidney|renal',
        ],
        [
            'specialty' => 'general_practice',
            'patterns' => [
                'fever', 'malaria', 'headache', 'cough', 'cold', 'flu', 'catarrh', 'sore throat', 'stomach', 'belly', 'diarrh', 'vomit',
                'rash', 'itch', 'pain', 'ache', 'tired', 'weak', 'dizzy', 'typhoid', 'infection', 'not feeling well', 'sick', 'ill\b', 'unwell',
                'ulcer', 'waist', 'joint', 'back pain', 'eye', 'ear', 'tooth', 'nausea', 'appetite', 'weight',
            ],
            'says' => 'A general practitioner is the right first call. They treat this directly, or refer you on if a specialist is needed.',
        ],
    ];

    private const GP_TESTS = [
        [['fever', 'malaria', 'chills', 'shiver'], 'MP', 'Find a malaria test'],
        [['typhoid', 'stomach.*fever', 'fever.*stomach'], 'WIDAL', 'Find a typhoid test'],
        [['tired', 'weak', 'dizzy', 'pale', 'anaemi', 'anemi'], 'FBC', 'Find a full blood count'],
        [['diarrh', 'stool', 'running stomach'], 'STOOLMCS', 'Find a stool test'],
        [['burning (when|while) (i )?(pee|urinat)', 'urine', 'pee'], 'URINALYSIS', 'Find a urine test'],
    ];

    private const SEE_SOON = [
        '\b(\d+|two|three|four|five|six|several|many) (days|weeks|months)\b', 'for weeks', 'for days', 'getting worse', '\bworse\b',
        'not (improving|getting better)', 'keeps coming back', 'blood in', "can'?t (eat|keep anything down)",
    ];

    private const LABELS = [
        'general_practice' => 'See a general practitioner',
        'cardiology' => 'See a cardiologist',
        'endocrinology' => 'See an endocrinologist',
        'nephrology' => 'See a nephrologist',
        'obstetrics' => 'See an obstetrician',
        'paediatrics' => 'See a paediatrician',
        'psychiatry' => 'See a psychiatrist',
        'other' => 'See a doctor',
    ];

    /**
     * @return array{specialty: string, patterns: list<string>, says: string, test?: array{0: string, 1: string}, condition?: string}|null
     */
    public function match(string $text): ?array
    {
        foreach (self::RULES as $rule) {
            if (Patterns::any($rule['patterns'], $text)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}|null the code and the action's label
     */
    public function generalTest(string $text): ?array
    {
        foreach (self::GP_TESTS as [$patterns, $code, $label]) {
            if (Patterns::any($patterns, $text)) {
                return [$code, $label];
            }
        }

        return null;
    }

    public function seeSoon(string $text): bool
    {
        return Patterns::any(self::SEE_SOON, $text);
    }

    public function label(string $specialty): string
    {
        return self::LABELS[$specialty];
    }
}
