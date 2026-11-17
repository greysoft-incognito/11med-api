<?php

namespace App\Services\Consultations;

use App\Models\DrugClassMember;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The prescribing cross-check (app handover §1.1, spec item 23): a drug conflicts with a recorded
 * allergy when either name contains the other, as the app checks, or when both fall in one drug
 * class, which the app cannot see: a penicillin allergy flags amoxicillin. The class list is a
 * curated seed awaiting a clinician's review (HT-009).
 */
class AllergyCheck
{
    /**
     * @param  list<array<string, mixed>>  $drugs  `zPrescribedDrug` items
     * @return list<array{drug_name: string, allergy_name: string, severity: ?string, drug_class: ?string}>
     */
    public function conflicts(User $patient, array $drugs): array
    {
        $allergies = $patient->medicalProfile?->drug_allergies ?? [];

        if ($allergies === []) {
            return [];
        }

        $allergyNames = array_map(fn(array $allergy) => $this->normalise((string) $allergy['drug_name']), $allergies);
        $drugNames = array_map(fn(array $drug) => $this->normalise((string) $drug['drug_name']), $drugs);
        $classes = $this->classesOf([...$allergyNames, ...$drugNames]);
        $conflicts = [];

        foreach ($drugs as $i => $drug) {
            foreach ($allergies as $j => $allergy) {
                $shared = array_values(array_intersect($classes[$drugNames[$i]] ?? [], $classes[$allergyNames[$j]] ?? []));

                if (! $this->namesMatch($drugNames[$i], $allergyNames[$j]) && $shared === []) {
                    continue;
                }

                $conflicts[] = [
                    'drug_name' => (string) $drug['drug_name'],
                    'allergy_name' => (string) $allergy['drug_name'],
                    'severity' => $allergy['severity'] ?? null,
                    'drug_class' => $shared[0] ?? null,
                ];
            }
        }

        return $conflicts;
    }

    /**
     * The class names each name belongs to, by whole words: "amoxicillin 500mg" is amoxicillin,
     * "mefenamic acid 250mg" is mefenamic acid.
     *
     * @param  list<string>  $names
     * @return array<string, list<string>>
     */
    private function classesOf(array $names): array
    {
        $words = collect($names)
            ->flatMap(fn(string $name) => explode(' ', $name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $members = DrugClassMember::query()
            ->join('drug_classes', 'drug_classes.id', '=', 'drug_class_members.drug_class_id')
            ->whereNull('drug_classes.deleted_at')
            ->where(fn($q) => $q
                ->whereIn('drug_class_members.name', $words)
                ->orWhereIn('drug_class_members.name', $names)
                ->orWhere('drug_class_members.name', 'like', '% %'))
            ->get(['drug_class_members.name', 'drug_classes.name as class_name']);

        $classes = [];

        foreach ($names as $name) {
            $classes[$name] = $members
                ->filter(fn($member) => str_contains(" {$name} ", " {$member->name} "))
                ->pluck('class_name')
                ->unique()
                ->values()
                ->all();
        }

        return $classes;
    }

    private function namesMatch(string $left, string $right): bool
    {
        return $left !== '' && $right !== '' && (str_contains($left, $right) || str_contains($right, $left));
    }

    private function normalise(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower($name)));
    }
}
