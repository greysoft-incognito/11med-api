<?php

namespace App\Services\Announcements;

use App\Enums\AnnouncementAudience;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The accounts an announcement reaches, as one query: those holding the audience's kinds, live and
 * not suspended.
 */
class AnnouncementAudiences
{
    /**
     * @return Builder<User>
     */
    public function query(AnnouncementAudience $audience): Builder
    {
        return User::query()
            ->ofKind($audience->kinds())
            ->whereNull('suspended_at');
    }

    public function count(AnnouncementAudience $audience): int
    {
        return $this
            ->query($audience)
            ->count();
    }
}
