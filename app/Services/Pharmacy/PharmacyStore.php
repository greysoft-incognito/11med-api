<?php

namespace App\Services\Pharmacy;

use App\Enums\MediaPurpose;
use App\Enums\OrderStatus;
use App\Enums\PartnerKind;
use App\Exceptions\ApiException;
use App\Models\Drug;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\User;
use App\Services\Media\MediaLibrary;
use App\Services\Partners\Organisations;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The pharmacy portal's own store (portal handover §4): the pharmacy behind an account, its
 * settings and its day at a glance.
 */
class PharmacyStore
{
    public function __construct(
        private readonly Organisations $organisations,
        private readonly MediaLibrary $media,
    ) {}

    /**
     * The pharmacy of a portal account; 404 `PHARMACY_NOT_FOUND` when it has none.
     */
    public function of(User $account): Pharmacy
    {
        $organisation = $this->organisations->of($account, PartnerKind::Pharmacy, Pharmacy::NOT_FOUND);

        $pharmacy = Pharmacy::query()
            ->where('organisation_id', $organisation->id)
            ->first();

        if ($pharmacy === null) {
            throw ApiException::notFound(Pharmacy::NOT_FOUND);
        }

        $pharmacy->setRelation('organisation', $organisation);

        return $pharmacy;
    }

    /**
     * The pharmacy as `zPharmacy` reads it, with its in-stock count.
     */
    public function show(User $account): Pharmacy
    {
        $pharmacy = $this->of($account);

        return $this->withCount($pharmacy);
    }

    /**
     * @param  array<string, mixed>  $input  The validated `zUpdatePharmacyRequest`.
     */
    public function update(User $account, array $input): Pharmacy
    {
        $pharmacy = $this->of($account);

        DB::transaction(function () use ($account, $pharmacy, $input) {
            $this->organisations->updateListing($pharmacy->organisation, $input);

            $logo = $this->media->replace($account, $pharmacy->logo, $input['logo_url'] ?? null, MediaPurpose::PharmacyLogo, 'logo_url');
            $cover = $this->media->replace($account, $pharmacy->cover, $input['cover_url'] ?? null, MediaPurpose::PharmacyCover, 'cover_url');

            $pharmacy->fill([
                'logo_media_id' => $logo?->id,
                'cover_media_id' => $cover?->id,
                'delivery_fee_kobo' => $input['delivery_fee_kobo'],
                'free_delivery_over_kobo' => $input['free_delivery_over_kobo'] ?? null,
                'delivery_minutes' => $input['delivery_minutes'] ?? null,
            ]);
            $pharmacy->save();
        });

        $pharmacy->unsetRelation('logo');
        $pharmacy->unsetRelation('cover');

        return $this->withCount($pharmacy);
    }

    /**
     * `zPharmacySummary`: counts in SQL, "today" in Lagos.
     *
     * @return array<string, int>
     */
    public function summary(User $account): array
    {
        $pharmacy = $this->of($account);
        $today = CarbonImmutable::now(config('elevenmed.timezone'))->startOfDay();

        $orders = Order::query()
            ->visibleToPharmacy()
            ->where('organisation_id', $pharmacy->organisation_id)
            ->toBase()
            ->selectRaw('count(*) filter (where status = ?) as new_orders', [OrderStatus::Placed->value])
            ->selectRaw('count(*) filter (where status in (?, ?)) as in_progress', [OrderStatus::Preparing->value, OrderStatus::Ready->value])
            ->selectRaw('count(*) filter (where status = ? and completed_at >= ?) as completed_today', [OrderStatus::Completed->value, $today->utc()])
            ->selectRaw('coalesce(sum(total_kobo) filter (where status = ? and completed_at >= ?), 0) as revenue_today_kobo', [OrderStatus::Completed->value, $today->utc()])
            ->first();

        $outOfStock = Drug::query()
            ->where('organisation_id', $pharmacy->organisation_id)
            ->where('in_stock', false)
            ->count();

        return [
            'new_orders' => (int) $orders->new_orders,
            'in_progress' => (int) $orders->in_progress,
            'completed_today' => (int) $orders->completed_today,
            'out_of_stock' => $outOfStock,
            'revenue_today_kobo' => (int) $orders->revenue_today_kobo,
        ];
    }

    private function withCount(Pharmacy $pharmacy): Pharmacy
    {
        return $pharmacy->loadCount(['drugs as in_stock_count' => fn($query) => $query->where('in_stock', true)]);
    }
}
