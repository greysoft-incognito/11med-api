<?php

namespace App\Services\Consultations;

use App\Enums\ConsultationStatus;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\Consultation;
use App\Models\User;
use App\Services\Audit\AuditLog;
use App\Support\LikePattern;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Every consultation, for the team: who, when, what was paid and where it stands, never the
 * complaint or the conversation. A booking that has not begun is cancelled through the lifecycle.
 */
class ConsultationDesk
{
    public const OPEN = 'open';

    public function __construct(
        private readonly ConsultationLifecycle $lifecycle,
        private readonly PaymentSummaries $summaries,
        private readonly AuditLog $audit,
    ) {}

    /**
     * Newest first; `q` over patient name and phone, and doctor name.
     */
    public function list(?string $status, ?string $search, ListQuery $list): Page
    {
        $search = trim((string) $search);
        $pattern = LikePattern::contains($search);
        $phone = preg_match(PhoneNumber::PATTERN, $search) === 1 ? LikePattern::contains(PhoneNumber::normalise($search)) : $pattern;

        $query = Consultation::query()
            ->with(['patient', 'doctor'])
            ->when($status === self::OPEN, fn(Builder $q) => $q->whereIn('status', ConsultationStatus::live()))
            ->when($status !== null && $status !== self::OPEN, fn(Builder $q) => $q->where('status', $status))
            ->when($search !== '', fn(Builder $q) => $q->where(fn(Builder $q) => $q
                ->whereLike('doctor_name', $pattern)
                ->orWhereHas('patient', fn(Builder $q) => $q
                    ->whereLike('full_name', $pattern)
                    ->orWhereLike('phone', $phone))))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $page = $list->paginate($query);
        $this->summaries->prime($page->items);

        return $page;
    }

    public function find(string $id): Consultation
    {
        return $this
            ->locate($id)
            ->load(['patient', 'doctor']);
    }

    public function cancel(User $actor, string $id, string $reason): Consultation
    {
        $consultation = DB::transaction(function () use ($actor, $id, $reason) {
            $consultation = $this->locate($id);
            $consultation = $this->lifecycle->cancelForTeam($consultation);

            $this->audit->record($actor, 'consultation.cancelled', $consultation, ['reason' => $reason]);

            return $consultation;
        });

        return $consultation->load(['patient', 'doctor']);
    }

    private function locate(string $id): Consultation
    {
        $consultation = Consultation::query()
            ->where('uuid', $id)
            ->first();

        return $consultation ?? throw ApiException::notFound(Consultation::NOT_FOUND);
    }
}
