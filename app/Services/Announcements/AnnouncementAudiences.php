<?php

namespace App\Services\Announcements;

use App\Enums\AnnouncementAudience;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The accounts an announcement reaches, as one query: the audience's roles, live and not suspended,
 * never a team account that also holds one of them.
 */
class AnnouncementAudiences
{
    /**
     * @return Builder<User>
     */
    public function query(AnnouncementAudience $audience): Builder
    {
        return User::query()
            ->role(Role::values($audience->roles()))
            ->withoutRole(Role::Admin->value)
            ->whereNull('suspended_at');
    }

    public function count(AnnouncementAudience $audience): int
    {
        return $this
            ->query($audience)
            ->count();
    }
}
