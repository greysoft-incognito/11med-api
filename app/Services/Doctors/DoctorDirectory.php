<?php

namespace App\Services\Doctors;

use App\Enums\ConsultLanguage;
use App\Enums\Specialty;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The doctors a patient sees (app `zDoctor`): approved ones only, available first, then by rating.
 * A doctor the patient is already with is not offered to them as available now.
 */
class DoctorDirectory
{
    public function __construct(
        private readonly EngagedDoctors $engaged,
    ) {}

    public function list(User $viewer, ListQuery $list, ?Specialty $specialty, ?ConsultLanguage $language, bool $availableNow, ?string $search): Page
    {
        $engaged = $this->engaged->for($viewer);

        $query = DoctorProfile::query()
            ->listed()
            ->with(['user', 'photo'])
            ->when($specialty !== null, fn(Builder $q) => $q->where('specialty', $specialty))
            ->when($language !== null, fn(Builder $q) => $q->whereJsonContains('languages', $language->value))
            ->when($availableNow, fn(Builder $q) => $q
                ->where('is_available_now', true)
                ->whereNotIn('user_id', $engaged))
            ->when(filled($search), fn(Builder $q) => $this->search($q, (string) $search))
            ->orderByDesc('is_available_now')
            ->orderByDesc('rating_mean')
            ->orderBy('id');

        $page = $list->paginate($query);

        $page->items->each(fn(DoctorProfile $profile) => $this->forViewer($profile, $engaged));

        return $page;
    }

    public function find(User $viewer, string $doctorId): DoctorProfile
    {
        $profile = $this->bookable($doctorId);

        $this->forViewer($profile, $this->engaged->for($viewer));

        return $profile;
    }

    /**
     * An approved doctor by user id, for the directory and for booking; 404 `DOCTOR_NOT_FOUND`
     * for anyone else, suspended doctors included.
     */
    public function bookable(string $doctorId): DoctorProfile
    {
        $profile = DoctorProfile::query()
            ->listed()
            ->whereHas('user', fn(Builder $q) => $q->where('uuid', $doctorId))
            ->first();

        if ($profile === null) {
            throw ApiException::notFound(DoctorProfile::NOT_FOUND);
        }

        return $profile->load(['user', 'photo']);
    }

    /**
     * The live toggle as this viewer sees it. Never saved.
     *
     * @param  list<int>  $engaged
     */
    private function forViewer(DoctorProfile $profile, array $engaged): void
    {
        $profile->is_available_now = $profile->is_available_now && ! in_array($profile->user_id, $engaged, true);
    }

    /**
     * Name, or specialty as words ("general practice").
     */
    private function search(Builder $query, string $search): void
    {
        $term = '%' . addcslashes(trim($search), '%_\\') . '%';

        $query->where(fn(Builder $q) => $q
            ->whereRaw("replace(specialty, '_', ' ') ilike ?", [$term])
            ->orWhereHas('user', fn(Builder $user) => $user->where('full_name', 'ilike', $term)));
    }
}
