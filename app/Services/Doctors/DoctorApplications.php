<?php

namespace App\Services\Doctors;

use App\Enums\DocumentKind;
use App\Enums\VerificationStatus;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\DoctorProfile;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The team's desk for doctors (portal handover §6 "Doctors"): submitted applications, oldest first,
 * each opened with its documents and decided through `DoctorVerification`.
 */
class DoctorApplications
{
    public function __construct(
        private readonly DoctorVerification $verification,
    ) {}

    /**
     * Applications that reached the queue at least once, oldest submission first, so the one waiting
     * longest is on top. A draft never submitted is not an application.
     */
    public function list(ListQuery $list, ?VerificationStatus $status, ?string $search): Page
    {
        $query = DoctorProfile::query()
            ->with(['user', 'photo', 'documents.media'])
            ->whereNotNull('submitted_at')
            ->when($status !== null, fn(Builder $q) => $q->where('verification_status', $status))
            ->when(filled($search), fn(Builder $q) => $this->search($q, (string) $search))
            ->orderBy('submitted_at')
            ->orderBy('id');

        return $list->paginate($query);
    }

    /**
     * Applications waiting for a decision, and how many whole hours the oldest has waited. One query.
     *
     * @return array{count: int, oldest_hours: int|null}
     */
    public function awaitingReview(): array
    {
        $row = DoctorProfile::query()
            ->whereNotNull('submitted_at')
            ->where('verification_status', VerificationStatus::Pending)
            ->selectRaw('count(*) as waiting, min(submitted_at) as oldest')
            ->toBase()
            ->first();

        $oldest = $row->oldest === null ? null : CarbonImmutable::parse($row->oldest);

        return [
            'count' => (int) $row->waiting,
            'oldest_hours' => $oldest === null ? null : max(0, (int) floor($oldest->diffInSeconds(now()) / 3600)),
        ];
    }

    public function find(string $doctorId): DoctorProfile
    {
        $profile = DoctorProfile::query()
            ->whereHas('user', fn(Builder $q) => $q->where('uuid', $doctorId))
            ->first();

        if ($profile === null) {
            throw ApiException::notFound(DoctorProfile::NOT_FOUND);
        }

        return $profile->load(['user', 'photo', 'documents.media']);
    }

    public function reviewDocument(string $doctorId, DocumentKind $kind, bool $accepted, ?string $reason): DoctorProfile
    {
        $profile = $this->find($doctorId);

        DB::transaction(function () use ($profile, $kind, $accepted, $reason) {
            $profile->newQuery()->lockForUpdate()->find($profile->id);

            $this->verification->reviewDocument($profile, $kind, $accepted, $reason);
        });

        return $profile->load('documents.media');
    }

    public function decide(string $doctorId, string $decision, ?string $notes): DoctorProfile
    {
        $profile = $this->find($doctorId);

        DB::transaction(function () use ($profile, $decision, $notes) {
            $profile->newQuery()->lockForUpdate()->find($profile->id);

            $this->verification->decide($profile, $decision, $notes);
        });

        return $profile;
    }

    /**
     * Name, MDCN number or phone, in either phone form.
     */
    private function search(Builder $query, string $search): void
    {
        $term = '%' . addcslashes(trim($search), '%_\\') . '%';
        $phone = '%' . addcslashes(PhoneNumber::normalise(trim($search)), '%_\\') . '%';

        $query->where(fn(Builder $q) => $q
            ->where('mdcn_number', 'ilike', $term)
            ->orWhereHas('user', fn(Builder $user) => $user
                ->where('full_name', 'ilike', $term)
                ->orWhere('phone', 'like', $phone)));
    }
}
