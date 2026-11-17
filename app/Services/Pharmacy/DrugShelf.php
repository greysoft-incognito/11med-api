<?php

namespace App\Services\Pharmacy;

use App\Enums\MediaPurpose;
use App\Enums\OrderStatus;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\Drug;
use App\Models\OrderItem;
use App\Models\Pharmacy;
use App\Models\User;
use App\Services\Media\MediaLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * A pharmacy's medicines, as its portal keeps them (portal handover §4).
 */
class DrugShelf
{
    public function __construct(
        private readonly MediaLibrary $media,
    ) {}

    /**
     * @param  array{q?: string|null, category?: string|null, stock?: string|null}  $filters
     */
    public function list(Pharmacy $pharmacy, array $filters, ListQuery $list): Page
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $stock = $filters['stock'] ?? null;

        $query = Drug::query()
            ->with(['organisation', 'image'])
            ->where('organisation_id', $pharmacy->organisation_id)
            ->when($filters['category'] ?? null, fn(Builder $query, string $category) => $query->where('category', $category))
            ->when($stock === 'out', fn(Builder $query) => $query->where('in_stock', false))
            ->when($stock === 'in', fn(Builder $query) => $query->where('in_stock', true))
            ->when($q !== '', fn(Builder $query) => $query->where(fn(Builder $query) => $query
                ->whereLike('name', "%{$q}%")
                ->orWhereLike('brand_name', "%{$q}%")
                ->orWhereLike('nafdac_number', "%{$q}%")
                ->orWhereLike('dosage_strength', "%{$q}%")))
            ->orderBy('name')
            ->orderBy('id');

        return $list->paginate($query);
    }

    public function find(Pharmacy $pharmacy, string $id): Drug
    {
        $drug = Drug::query()
            ->with(['organisation', 'image'])
            ->where('organisation_id', $pharmacy->organisation_id)
            ->where('uuid', $id)
            ->first();

        if ($drug === null) {
            throw ApiException::notFound(Drug::NOT_FOUND);
        }

        return $drug;
    }

    /**
     * @param  array<string, mixed>  $input  The validated `zUpsertDrugRequest`.
     */
    public function create(User $account, Pharmacy $pharmacy, array $input): Drug
    {
        $drug = new Drug(['organisation_id' => $pharmacy->organisation_id]);

        return $this->save($account, $drug, $input);
    }

    /**
     * @param  array<string, mixed>  $input  The validated `zUpsertDrugRequest`.
     */
    public function update(User $account, Pharmacy $pharmacy, string $id, array $input): Drug
    {
        $drug = $this->find($pharmacy, $id);

        return $this->save($account, $drug, $input);
    }

    /**
     * Removed only when no order still to be delivered holds it; marking it out of stock is the way otherwise.
     */
    public function delete(Pharmacy $pharmacy, string $id): void
    {
        $drug = $this->find($pharmacy, $id);

        $held = OrderItem::query()
            ->where('drug_id', $drug->id)
            ->whereHas('order', fn(Builder $query) => $query->whereIn('status', OrderStatus::open()))
            ->exists();

        if ($held) {
            throw ApiException::unprocessable('DRUG_IN_OPEN_ORDER');
        }

        $drug->delete();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function save(User $account, Drug $drug, array $input): Drug
    {
        DB::transaction(function () use ($account, $drug, $input) {
            $duplicate = Drug::query()
                ->where('organisation_id', $drug->organisation_id)
                ->whereRaw('lower(nafdac_number) = ?', [mb_strtolower($input['nafdac_number'])])
                ->when($drug->exists, fn(Builder $query) => $query->whereKeyNot($drug->id))
                ->exists();

            if ($duplicate) {
                throw ApiException::conflict('DUPLICATE_NAFDAC', 'nafdac_number');
            }

            $image = $this->media->replace($account, $drug->image, $input['image_url'] ?? null, MediaPurpose::DrugImage, 'image_url');

            $drug->fill([
                'name' => $input['name'],
                'brand_name' => $input['brand_name'] ?? null,
                'nafdac_number' => $input['nafdac_number'],
                'category' => $input['category'],
                'dosage_form' => $input['dosage_form'],
                'dosage_strength' => $input['dosage_strength'],
                'description' => $input['description'] ?? '',
                'price_kobo' => $input['price_kobo'],
                'requires_prescription' => $input['requires_prescription'],
                'in_stock' => $input['in_stock'],
                'image_media_id' => $image?->id,
            ]);
            $drug->save();
        });

        $drug->unsetRelation('image');

        return $drug->load(['organisation', 'image']);
    }
}
