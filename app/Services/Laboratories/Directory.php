<?php

namespace App\Services\Laboratories;

use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\Laboratory;
use App\Models\LaboratoryTest;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;

/**
 * The app's laboratory directory (app handover §10): listed laboratories only, ranked by the
 * patient's state first, then rating, the unrated last. Every laboratory listed is MLSCN-licensed,
 * which approval checked.
 */
class Directory
{
    /**
     * `q` matches name, city and state; `test` keeps the laboratories that can run that code now
     * and fills `test_price_kobo` with each one's price. Paged (FE-003).
     */
    public function search(?string $homeState, ?string $search, ?string $testCode, ListQuery $list): Page
    {
        $search = trim((string) $search);
        $pattern = LikePattern::contains($search);
        $testCode = $testCode === null || trim($testCode) === '' ? null : strtoupper(trim($testCode));

        $price = LaboratoryTest::query()
            ->select('laboratory_tests.price_kobo')
            ->join('lab_tests', 'lab_tests.id', '=', 'laboratory_tests.lab_test_id')
            ->whereColumn('laboratory_tests.laboratory_id', 'laboratories.id')
            ->where('laboratory_tests.available', true)
            ->where('lab_tests.code', $testCode)
            ->whereNull('lab_tests.deleted_at')
            ->limit(1);

        $ranked = Laboratory::query()
            ->listed()
            ->join('organisations', 'organisations.id', '=', 'laboratories.organisation_id')
            ->select('laboratories.*')
            ->withTestsCount()
            ->selectRaw('case when organisations.state = ? then 1 else 0 end as home_rank', [(string) $homeState])
            ->selectRaw('coalesce(laboratories.rating, 0) as rating_rank')
            ->selectRaw('organisations.name as sort_name')
            ->when($testCode !== null, fn(Builder $q) => $q->addSelect(['test_price_kobo' => $price]))
            ->when($search !== '', fn(Builder $q) => $q->where(fn(Builder $w) => $w
                ->whereLike('organisations.name', $pattern)
                ->orWhereLike('organisations.city', $pattern)
                ->orWhereLike('organisations.state', $pattern)));

        $query = Laboratory::query()
            ->fromSub($ranked, 'laboratories')
            ->with(['organisation', 'logo', 'cover'])
            ->when($testCode !== null, fn(Builder $q) => $q->whereNotNull('test_price_kobo'))
            ->orderByDesc('home_rank')
            ->orderByDesc('rating_rank')
            ->orderBy('sort_name')
            ->orderBy('id');

        return $list->paginate($query);
    }

    /**
     * One listed laboratory by its public id (the organisation's uuid); 404 `LAB_NOT_FOUND`.
     */
    public function find(string $laboratoryId): Laboratory
    {
        $laboratory = Laboratory::query()
            ->listed()
            ->with(['organisation', 'logo', 'cover'])
            ->withTestsCount()
            ->whereHas('organisation', fn(Builder $q) => $q->where('uuid', $laboratoryId))
            ->first();

        if ($laboratory === null) {
            throw ApiException::notFound(Laboratory::NOT_FOUND);
        }

        return $laboratory;
    }
}
