<?php

namespace App\Services\Pharmacy;

use App\Enums\PartnerKind;
use App\Enums\PartnerStatus;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\Drug;
use App\Models\MedicalProfile;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The app's store (app spec §8.1, handover §5.3): listed pharmacies and their medicines, the
 * patient's own state first, then by rating with the unrated last, then by name.
 */
class Storefront
{
    /**
     * @param  array{q?: string|null}  $filters
     */
    public function pharmacies(User $patient, array $filters, ListQuery $list): Page
    {
        $q = trim((string) ($filters['q'] ?? ''));

        $ranked = $this
            ->listedPharmacies()
            ->addSelect('pharmacies.*')
            ->selectRaw('coalesce((organisations.state = ?)::int, 0) as home_rank', [$this->homeState($patient)])
            ->selectRaw('coalesce(pharmacies.rating, 0) as rating_rank')
            ->selectRaw('organisations.name as sort_name')
            ->when($q !== '', fn(Builder $query) => $query->where(fn(Builder $query) => $query
                ->whereLike('organisations.name', "%{$q}%")
                ->orWhereLike('organisations.city', "%{$q}%")
                ->orWhereLike('organisations.state', "%{$q}%")));

        $query = Pharmacy::query()
            ->fromSub($ranked->toBase(), 'pharmacies')
            ->with(['organisation', 'logo', 'cover'])
            ->orderByDesc('home_rank')
            ->orderByDesc('rating_rank')
            ->orderBy('sort_name')
            ->orderBy('id');

        return $list->paginate($query);
    }

    public function pharmacy(string $id): Pharmacy
    {
        $pharmacy = $this
            ->listedPharmacies()
            ->addSelect('pharmacies.*')
            ->where('organisations.uuid', $id)
            ->with(['organisation', 'logo', 'cover'])
            ->first();

        if ($pharmacy === null) {
            throw ApiException::notFound(Pharmacy::NOT_FOUND);
        }

        return $pharmacy;
    }

    /**
     * @param  array{q?: string|null, category?: string|null, in_stock?: string|null, requires_prescription?: string|null, pharmacy_id?: string|null}  $filters
     */
    public function drugs(User $patient, array $filters, ListQuery $list): Page
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $inStockOnly = ($filters['in_stock'] ?? 'true') !== 'false';
        $prescription = $filters['requires_prescription'] ?? null;

        $ranked = $this
            ->listedDrugs()
            ->select('drugs.*')
            ->selectRaw('coalesce((organisations.state = ?)::int, 0) as home_rank', [$this->homeState($patient)])
            ->selectRaw('coalesce(pharmacies.rating, 0) as rating_rank')
            ->when($filters['pharmacy_id'] ?? null, fn(Builder $query, string $id) => $query->where('organisations.uuid', $id))
            ->when($filters['category'] ?? null, fn(Builder $query, string $category) => $query->where('drugs.category', $category))
            ->when($prescription !== null, fn(Builder $query) => $query->where('drugs.requires_prescription', $prescription === 'true'))
            ->when($inStockOnly, fn(Builder $query) => $query->where('drugs.in_stock', true))
            ->when($q !== '', fn(Builder $query) => $query->where(fn(Builder $query) => $query
                ->whereLike('drugs.name', "%{$q}%")
                ->orWhereLike('drugs.brand_name', "%{$q}%")
                ->orWhereLike('drugs.nafdac_number', "%{$q}%")));

        $query = Drug::query()
            ->fromSub($ranked->toBase(), 'drugs')
            ->with(['organisation', 'image'])
            ->orderByDesc('home_rank')
            ->orderByDesc('rating_rank')
            ->orderBy('name')
            ->orderBy('id');

        return $list->paginate($query);
    }

    /**
     * A listed medicine; 404 `DRUG_NOT_FOUND` once removed or its pharmacy delisted.
     */
    public function drug(string $id): Drug
    {
        $drug = $this
            ->listedDrugs()
            ->select('drugs.*')
            ->where('drugs.uuid', $id)
            ->with(['organisation', 'image'])
            ->first();

        if ($drug === null) {
            throw ApiException::notFound(Drug::NOT_FOUND);
        }

        return $drug;
    }

    /**
     * Medicines a patient can buy now: not removed, from an approved pharmacy.
     */
    public function listedDrugs(): Builder
    {
        return Drug::query()
            ->join('organisations', 'organisations.id', '=', 'drugs.organisation_id')
            ->join('pharmacies', 'pharmacies.organisation_id', '=', 'organisations.id')
            ->where('organisations.kind', PartnerKind::Pharmacy)
            ->where('organisations.status', PartnerStatus::Approved)
            ->whereNull('organisations.deleted_at')
            ->whereNull('pharmacies.deleted_at');
    }

    /**
     * Approved pharmacies, each with its count of in-stock medicines.
     */
    private function listedPharmacies(): Builder
    {
        return Pharmacy::query()
            ->join('organisations', 'organisations.id', '=', 'pharmacies.organisation_id')
            ->selectRaw('(select count(*) from drugs where drugs.organisation_id = pharmacies.organisation_id and drugs.in_stock and drugs.deleted_at is null) as in_stock_count')
            ->where('organisations.kind', PartnerKind::Pharmacy)
            ->where('organisations.status', PartnerStatus::Approved)
            ->whereNull('organisations.deleted_at');
    }

    private function homeState(User $patient): ?string
    {
        return MedicalProfile::query()
            ->where('user_id', $patient->id)
            ->value('state_of_residence');
    }
}
