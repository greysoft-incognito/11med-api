<?php

namespace App\Services\Laboratories;

use App\Exceptions\ApiException;
use App\Models\LaboratoryTest;
use App\Models\LabTest;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;

/**
 * The platform's test catalogue (portal handover §6 "The test catalogue"): the team adds and
 * edits tests, every laboratory prices them, and nothing else invents one. Small, read whole.
 */
class Catalogue
{
    /**
     * @return Collection<int, LabTest>
     */
    public function all(?string $search = null, ?string $category = null): Collection
    {
        return $this
            ->search($search, $category)
            ->get();
    }

    /**
     * Every test with `from_kobo`, the cheapest price a listed laboratory can run it for now,
     * and `offered_by`, how many can (app handover §10).
     *
     * @return Collection<int, LabTest>
     */
    public function withPrices(?string $search = null, ?string $category = null): Collection
    {
        $offers = LaboratoryTest::query()
            ->whereColumn('laboratory_tests.lab_test_id', 'lab_tests.id')
            ->where('available', true)
            ->whereHas('laboratory', fn(Builder $q) => $q->listed());

        return $this
            ->search($search, $category)
            ->addSelect([
                'from_kobo' => $offers
                    ->clone()
                    ->selectRaw('min(price_kobo)'),
                'offered_by' => $offers
                    ->clone()
                    ->selectRaw('count(*)'),
            ])
            ->get();
    }

    public function find(string $code): LabTest
    {
        $test = LabTest::query()
            ->where('code', strtoupper($code))
            ->first();

        if ($test === null) {
            throw ApiException::notFound(LabTest::NOT_FOUND);
        }

        return $test;
    }

    /**
     * The tests behind `$codes`, keyed by code. Codes the catalogue lacks are absent.
     *
     * @param  list<string>  $codes
     * @return Collection<string, LabTest>
     */
    public function byCodes(array $codes): Collection
    {
        return LabTest::query()
            ->whereIn('code', array_map('strtoupper', $codes))
            ->get()
            ->keyBy('code');
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function create(array $fields): LabTest
    {
        if (LabTest::query()->where('code', $fields['code'])->exists()) {
            throw ApiException::conflict('DUPLICATE_CODE', 'code');
        }

        try {
            return LabTest::create($fields);
        } catch (QueryException $e) {
            throw $e->getCode() === '23505' ? ApiException::conflict('DUPLICATE_CODE', 'code') : $e;
        }
    }

    /**
     * Everything but the code, which price lists, requests and bookings point at. Price lists
     * read the catalogue row, so every laboratory's copy follows the edit.
     *
     * @param  array<string, mixed>  $fields
     */
    public function update(string $code, array $fields): LabTest
    {
        $test = $this->find($code);

        if ($fields['code'] !== $test->code) {
            throw ApiException::unprocessable('CODE_IMMUTABLE', 'code');
        }

        $test->update($fields);

        return $test;
    }

    /**
     * The catalogue, filtered by `q` over code, name and description, and by category, by name.
     */
    public function search(?string $search, ?string $category): Builder
    {
        $search = trim((string) $search);
        $pattern = LikePattern::contains($search);

        return LabTest::query()
            ->select('lab_tests.*')
            ->when($category, fn(Builder $q) => $q->where('category', $category))
            ->when($search !== '', fn(Builder $q) => $q->where(fn(Builder $w) => $w
                ->whereLike('code', $pattern)
                ->orWhereLike('name', $pattern)
                ->orWhereLike('description', $pattern)))
            ->orderBy('name')
            ->orderBy('id');
    }
}
