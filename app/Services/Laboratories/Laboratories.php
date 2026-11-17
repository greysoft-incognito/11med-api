<?php

namespace App\Services\Laboratories;

use App\Enums\LabBookingStatus;
use App\Enums\MediaPurpose;
use App\Enums\PartnerKind;
use App\Exceptions\ApiException;
use App\Models\LabBooking;
use App\Models\Laboratory;
use App\Models\Media;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Media\MediaLibrary;
use App\Services\Partners\Organisations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A laboratory's own listing, as its portal account manages it (portal handover §5): opening
 * hours, home collection, logo and cover here; name and address through its organisation.
 */
class Laboratories
{
    public function __construct(
        private readonly MediaLibrary $media,
        private readonly Organisations $organisations,
    ) {}

    /**
     * The laboratory a portal account works for; 404 `LAB_NOT_FOUND` when it has none.
     */
    public function of(User $account): Laboratory
    {
        $organisation = $this->organisations->of($account, PartnerKind::Laboratory, Laboratory::NOT_FOUND);

        $laboratory = Laboratory::query()
            ->with(['logo', 'cover'])
            ->withTestsCount()
            ->where('organisation_id', $organisation->id)
            ->first()
            ?->setRelation('organisation', $organisation);

        if ($laboratory === null) {
            throw ApiException::notFound(Laboratory::NOT_FOUND);
        }

        return $laboratory;
    }

    /**
     * The listing an approved laboratory starts with, which it then edits in Settings.
     */
    public function open(Organisation $organisation): Laboratory
    {
        return Laboratory::query()->firstOrCreate(
            ['organisation_id' => $organisation->id],
            ['opens' => Laboratory::DEFAULT_OPENS, 'closes' => Laboratory::DEFAULT_CLOSES],
        );
    }

    /**
     * @param  array<string, mixed>  $data  `zUpdateLaboratoryRequest`
     */
    public function update(User $account, array $data): Laboratory
    {
        $laboratory = $this->of($account);

        DB::transaction(function () use ($account, $laboratory, $data) {
            $this->organisations->updateListing($laboratory->organisation, [
                'name' => $data['name'],
                'state' => $data['state'],
                'city' => $data['city'] ?? null,
                'address' => $data['address'] ?? null,
            ]);

            $laboratory->fill([
                'opens' => $data['opens'],
                'closes' => $data['closes'],
                'home_collection_fee_kobo' => $data['home_collection_fee_kobo'],
            ]);

            $this->setPicture($account, $laboratory, 'logo', $data['logo_url'] ?? null, MediaPurpose::LaboratoryLogo, 'logo_url');
            $this->setPicture($account, $laboratory, 'cover', $data['cover_url'] ?? null, MediaPurpose::LaboratoryCover, 'cover_url');

            $laboratory->save();
        });

        return $this->of($account);
    }

    /**
     * `zLaboratorySummary`, counted in SQL over paid bookings. Today and the week are Lagos days.
     *
     * @return array<string, int>
     */
    public function summary(Laboratory $laboratory): array
    {
        $zone = config('elevenmed.timezone');
        $today = Carbon::now($zone)->startOfDay();
        $week = Carbon::now($zone)->subDays(7);

        $bookings = LabBooking::query()
            ->where('laboratory_id', $laboratory->id)
            ->whereNotNull('paid_at')
            ->selectRaw('count(*) filter (where status = ? and scheduled_for >= ? and scheduled_for < ?) as booked_today', [
                LabBookingStatus::Booked->value,
                $today->copy()->utc(),
                $today->copy()->addDay()->utc(),
            ])
            ->selectRaw('count(*) filter (where status in (?, ?)) as awaiting_results', [
                LabBookingStatus::SampleCollected->value,
                LabBookingStatus::Processing->value,
            ])
            ->selectRaw('count(*) filter (where results_at >= ?) as results_this_week', [$week->utc()])
            ->first();

        $tests = $laboratory
            ->offers()
            ->selectRaw('count(*) as listed, count(*) filter (where not available) as unavailable')
            ->first();

        return [
            'booked_today' => (int) $bookings->booked_today,
            'awaiting_results' => (int) $bookings->awaiting_results,
            'results_this_week' => (int) $bookings->results_this_week,
            'tests_listed' => (int) $tests->listed,
            'tests_unavailable' => (int) $tests->unavailable,
        ];
    }

    private function setPicture(User $owner, Laboratory $laboratory, string $relation, ?string $url, MediaPurpose $purpose, string $field): void
    {
        /** @var Media|null $previous */
        $previous = $laboratory->{$relation};

        if ($url !== null && $previous !== null && strtok($url, '?') === strtok($this->media->url($previous), '?')) {
            return;
        }

        $media = $url === null ? null : $this->media->claim($owner, $url, $purpose, $field);

        $laboratory->{"{$relation}_media_id"} = $media?->id;
        $laboratory->setRelation($relation, $media);

        if ($previous !== null && ! $previous->is($media)) {
            $this->media->discard($previous);
        }
    }
}
