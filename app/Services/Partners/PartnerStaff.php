<?php

namespace App\Services\Partners;

use App\Enums\PartnerStatus;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Identity\PortalAccounts;
use App\Services\Identity\Sessions;
use Illuminate\Support\Facades\DB;

/**
 * A pharmacy's or laboratory's own staff: every member sees the list; the owner invites by email,
 * removes members and hands ownership to another member who has set a password.
 */
class PartnerStaff
{
    public const NOT_FOUND = 'STAFF_NOT_FOUND';

    public function __construct(
        private readonly Organisations $organisations,
        private readonly PortalAccounts $accounts,
        private readonly Sessions $sessions,
    ) {}

    public function list(User $member, ListQuery $list): Page
    {
        $organisation = $this->organisationOf($member);

        $query = User::query()
            ->with('organisation')
            ->where('organisation_id', $organisation->id)
            ->orderBy('id');

        return $list->paginate($query);
    }

    /**
     * @param  array{name: string, email: string}  $input
     */
    public function invite(User $owner, array $input): User
    {
        return DB::transaction(function () use ($owner, $input) {
            $organisation = $this->ownedBy($owner);
            $email = mb_strtolower($input['email']);

            $taken = User::query()
                ->whereRaw('lower(email) = ?', [$email])
                ->exists();

            if ($taken) {
                throw ApiException::conflict('EMAIL_TAKEN', 'email');
            }

            $account = new User([
                'full_name' => $input['name'],
                'email' => $email,
            ]);
            $account->organisation_id = $organisation->id;
            $account->email_verified_at = now();
            $account->save();
            $account->assignRole($organisation->kind->role()->value);
            $account->setRelation('organisation', $organisation);

            $this->accounts->welcome($account);

            return $account;
        });
    }

    /**
     * Remove a member: their sessions end, their unused links stop working, and their username is released.
     */
    public function remove(User $owner, string $id): void
    {
        DB::transaction(function () use ($owner, $id) {
            $organisation = $this->ownedBy($owner);
            $member = $this->member($organisation, $id);

            if ($member->id === $organisation->owner_id) {
                throw ApiException::unprocessable('STAFF_IS_OWNER');
            }

            $this->sessions->revokeAll($member);

            $member->forceFill(['username' => null])->save();
            $member->delete();
        });
    }

    /**
     * Hand the organisation to another member, who must have set a password.
     */
    public function makeOwner(User $owner, string $id): User
    {
        return DB::transaction(function () use ($owner, $id) {
            $organisation = $this->ownedBy($owner);
            $member = $this->member($organisation, $id);

            if ($member->password === null) {
                throw ApiException::unprocessable('STAFF_NOT_ACTIVE');
            }

            $this->organisations->transferOwnership($organisation, $member);
            $member->setRelation('organisation', $organisation);

            return $member;
        });
    }

    /**
     * Whether the account owns its organisation.
     */
    public function isOwner(User $account): bool
    {
        $organisation = $account->organisation;

        return $organisation !== null && $organisation->owner_id === $account->id;
    }

    private function organisationOf(User $member): Organisation
    {
        $organisation = $member->organisation;

        if ($organisation === null || $organisation->status === PartnerStatus::Pending) {
            throw ApiException::notFound(Organisation::NOT_FOUND);
        }

        return $organisation;
    }

    /**
     * The caller's organisation, locked, when the caller owns it.
     */
    private function ownedBy(User $owner): Organisation
    {
        $organisation = Organisation::query()
            ->whereKey($this->organisationOf($owner)->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($organisation->owner_id !== $owner->id) {
            throw ApiException::forbidden('NOT_OWNER');
        }

        return $organisation;
    }

    private function member(Organisation $organisation, string $id): User
    {
        $member = User::query()
            ->where('organisation_id', $organisation->id)
            ->where('uuid', $id)
            ->first();

        if ($member === null) {
            throw ApiException::notFound(self::NOT_FOUND);
        }

        return $member;
    }
}
