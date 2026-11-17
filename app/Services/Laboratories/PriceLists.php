<?php

namespace App\Services\Laboratories;

use App\Enums\LabBookingStatus;
use App\Exceptions\ApiException;
use App\Models\LabBooking;
use App\Models\Laboratory;
use App\Models\LaboratoryTest;
use App\Models\LabTest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * A laboratory's price list (portal handover §5): catalogue tests it offers, each with its own
 * price and whether it can run it now. Small, read whole. Each entry carries its catalogue test,
 * so an edit to the catalogue shows on every list at once.
 */
class PriceLists
{
    public function __construct(
        private readonly Catalogue $catalogue,
    ) {}

    /**
     * The laboratory's list, by test name, filtered like the catalogue.
     *
     * @return Collection<int, LaboratoryTest>
     */
    public function of(Laboratory $laboratory, ?string $search = null, ?string $category = null): Collection
    {
        $tests = $this->catalogue
            ->search($search, $category)
            ->select('lab_tests.id');

        return LaboratoryTest::query()
            ->select('laboratory_tests.*')
            ->join('lab_tests', 'lab_tests.id', '=', 'laboratory_tests.lab_test_id')
            ->with('test')
            ->where('laboratory_id', $laboratory->id)
            ->whereIn('lab_test_id', $tests)
            ->orderBy('lab_tests.name')
            ->orderBy('laboratory_tests.id')
            ->get();
    }

    /**
     * The whole catalogue with this laboratory's price and availability where it lists the test.
     *
     * @return Collection<int, LabTest>
     */
    public function catalogueFor(Laboratory $laboratory, ?string $search = null, ?string $category = null): Collection
    {
        return $this->catalogue
            ->search($search, $category)
            ->leftJoin('laboratory_tests', fn($join) => $join
                ->on('laboratory_tests.lab_test_id', '=', 'lab_tests.id')
                ->where('laboratory_tests.laboratory_id', $laboratory->id)
                ->whereNull('laboratory_tests.deleted_at'))
            ->addSelect([
                'laboratory_tests.price_kobo as offered_price_kobo',
                'laboratory_tests.available as offered_available',
            ])
            ->get();
    }

    /**
     * Price, or re-price, one catalogue test. Returns the entry and whether it is new.
     *
     * @return array{0: LaboratoryTest, 1: bool}
     */
    public function set(Laboratory $laboratory, string $code, int $priceKobo, bool $available): array
    {
        $test = $this->catalogue
            ->byCodes([$code])
            ->first();

        if ($test === null) {
            throw ApiException::unprocessable('UNKNOWN_TEST', 'test_code');
        }

        $entry = LaboratoryTest::query()->updateOrCreate(
            ['laboratory_id' => $laboratory->id, 'lab_test_id' => $test->id],
            ['price_kobo' => $priceKobo, 'available' => $available],
        );

        $entry->setRelation('test', $test);

        return [$entry, $entry->wasRecentlyCreated];
    }

    /**
     * Take a test off the list; refused while a booking in progress includes it.
     */
    public function remove(Laboratory $laboratory, string $code): void
    {
        $entry = LaboratoryTest::query()
            ->where('laboratory_id', $laboratory->id)
            ->whereHas('test', fn(Builder $q) => $q->where('code', strtoupper($code)))
            ->first();

        if ($entry === null) {
            throw ApiException::notFound(LaboratoryTest::NOT_FOUND);
        }

        $open = LabBooking::query()
            ->where('laboratory_id', $laboratory->id)
            ->whereIn('status', LabBookingStatus::openValues())
            ->whereJsonContains('tests', [['test_code' => strtoupper($code)]])
            ->exists();

        if ($open) {
            throw ApiException::unprocessable('TEST_IN_OPEN_BOOKING');
        }

        $entry->delete();
    }
}
