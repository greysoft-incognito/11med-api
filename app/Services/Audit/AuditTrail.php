<?php

namespace App\Services\Audit;

use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\AuditEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reads the audit log, newest first. Dates filter by the Lagos calendar day.
 */
class AuditTrail
{
    /**
     * @param  array{actor?: ?string, action?: ?string, subject_type?: ?string, subject_id?: ?string, from?: ?string, to?: ?string}  $filters
     */
    public function list(array $filters, ListQuery $list): Page
    {
        $zone = config('elevenmed.timezone');
        $from = isset($filters['from']) ? CarbonImmutable::parse($filters['from'], $zone)->startOfDay()->utc() : null;
        $to = isset($filters['to']) ? CarbonImmutable::parse($filters['to'], $zone)->addDay()->startOfDay()->utc() : null;

        $query = AuditEvent::query()
            ->with('actor:id,uuid')
            ->when(isset($filters['actor']), fn(Builder $q) => $q->whereIn(
                'actor_id',
                User::query()
                    ->select('id')
                    ->where('uuid', $filters['actor'])
            ))
            ->when(isset($filters['action']), fn(Builder $q) => $q->where('action', $filters['action']))
            ->when(isset($filters['subject_type']), fn(Builder $q) => $q->where('subject_type', $filters['subject_type']))
            ->when(isset($filters['subject_id']), fn(Builder $q) => $q->where('subject_id', $filters['subject_id']))
            ->when($from !== null, fn(Builder $q) => $q->where('created_at', '>=', $from))
            ->when($to !== null, fn(Builder $q) => $q->where('created_at', '<', $to))
            ->orderByDesc('id');

        return $list->paginate($query);
    }
}
