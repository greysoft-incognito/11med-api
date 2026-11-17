<?php

namespace App\Services\Medications;

use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\DoseLog;
use App\Models\User;
use App\Services\Subscriptions\Entitlements;
use Carbon\CarbonImmutable;

/**
 * Settled doses, newest first (screen 40): taken, late, skipped, snoozed and missed alike, since a
 * history of only the answered doses hides the ones worth reviewing. Doses not yet due are not history.
 */
class DoseHistory
{
    public function __construct(
        private readonly Entitlements $entitlements,
    ) {}

    /**
     * The window the record's owner is entitled to read, whoever asks (spec §12).
     */
    public function allowedDays(User $owner, int $asked): int
    {
        $limit = $this->entitlements->historyDays($owner);

        return $limit === null ? $asked : min($asked, $limit);
    }

    public function of(User $patient, int $days, ?string $medicationId, ListQuery $list): Page
    {
        $now = CarbonImmutable::now();

        $query = DoseLog::query()
            ->with('medication')
            ->whereHas('medication', fn($q) => $q->when($medicationId !== null, fn($q) => $q->where('uuid', $medicationId)))
            ->where('user_id', $patient->id)
            ->where('scheduled_for', '>', $now->subDays($days))
            ->where('scheduled_for', '<=', $now)
            ->orderByDesc('scheduled_for')
            ->orderByDesc('id');

        $page = $list->paginate($query);
        $doses = $page->items->map(fn(DoseLog $log) => new Dose($log->medication, $log->scheduled_for, $log));

        return new Page($doses, $page->nextCursor, $page->total);
    }
}
