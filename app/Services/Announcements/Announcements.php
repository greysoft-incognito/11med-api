<?php

namespace App\Services\Announcements;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementStatus;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Jobs\Announcements\SendAnnouncement;
use App\Models\Announcement;
use App\Models\User;
use App\Services\Audit\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The team's announcements: written for an audience, sent now or at a time, withdrawn before then.
 */
class Announcements
{
    public function __construct(
        private readonly AnnouncementAudiences $audiences,
        private readonly AuditLog $audit,
    ) {}

    public function list(ListQuery $list): Page
    {
        $query = Announcement::query()
            ->with('author')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return $list->paginate($query);
    }

    public function reach(AnnouncementAudience $audience): int
    {
        return $this->audiences->count($audience);
    }

    /**
     * @param  array{title: string, body: string, audience: string, push: bool, send_at?: string|null}  $data
     */
    public function create(User $actor, array $data): Announcement
    {
        $sendAt = isset($data['send_at']) ? CarbonImmutable::parse($data['send_at']) : null;
        $now = $sendAt === null;

        $announcement = DB::transaction(function () use ($actor, $data, $sendAt, $now) {
            $announcement = Announcement::create([
                'title' => $data['title'],
                'body' => $data['body'],
                'audience' => $data['audience'],
                'push' => $data['push'],
                'status' => $now ? AnnouncementStatus::Sending : AnnouncementStatus::Scheduled,
                'send_at' => $sendAt ?? now(),
                'created_by' => $actor->id,
            ]);

            $this->audit->record($actor, $now ? 'announcement.sent' : 'announcement.scheduled', $announcement, [
                'audience' => $announcement->audience->value,
                'push' => $announcement->push,
            ]);

            return $announcement;
        });

        if ($now) {
            SendAnnouncement::dispatch($announcement->id)->afterCommit();
        }

        return $announcement->refresh()->load('author');
    }

    public function cancel(User $actor, string $id): Announcement
    {
        $announcement = DB::transaction(function () use ($actor, $id) {
            $announcement = Announcement::query()
                ->where('uuid', $id)
                ->lockForUpdate()
                ->first() ?? throw ApiException::notFound(Announcement::NOT_FOUND);

            if ($announcement->status !== AnnouncementStatus::Scheduled) {
                throw ApiException::unprocessable('ANNOUNCEMENT_NOT_SCHEDULED');
            }

            $announcement->forceFill([
                'status' => AnnouncementStatus::Cancelled,
                'cancelled_at' => now(),
            ])->save();

            $this->audit->record($actor, 'announcement.cancelled', $announcement);

            return $announcement;
        });

        return $announcement->load('author');
    }

    /**
     * Starts every scheduled announcement whose time has come, each once, however many schedulers run.
     */
    public function dispatchDue(): int
    {
        $due = DB::transaction(function () {
            $ids = Announcement::query()
                ->where('status', AnnouncementStatus::Scheduled)
                ->where('send_at', '<=', now())
                ->orderBy('send_at')
                ->limit(50)
                ->lock('FOR UPDATE SKIP LOCKED')
                ->pluck('id');

            Announcement::query()
                ->whereIn('id', $ids)
                ->update(['status' => AnnouncementStatus::Sending]);

            return $ids;
        });

        $due->each(fn(int $id) => SendAnnouncement::dispatch($id));

        return $due->count();
    }
}
