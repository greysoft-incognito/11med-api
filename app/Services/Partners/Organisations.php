<?php

namespace App\Services\Partners;

use App\Enums\MediaPurpose;
use App\Enums\PartnerKind;
use App\Enums\PartnerStatus;
use App\Events\Partners\PartnerApplied;
use App\Events\Partners\PartnerApproved;
use App\Events\Partners\PartnerReinstated;
use App\Events\Partners\PartnerSuspended;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\Partners\PartnerStatusChanged;
use App\Services\Audit\AuditLog;
use App\Services\Identity\PortalAccounts;
use App\Services\Media\MediaLibrary;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

/**
 * Partners (concept §5.1, portal handover §6): a pharmacy or laboratory applies with its licence,
 * the team approves it, which gives its contact an account, and may suspend and reinstate it.
 * The only writer of `organisations`.
 */
class Organisations
{
    public function __construct(
        private readonly MediaLibrary $media,
        private readonly PortalAccounts $accounts,
        private readonly AuditLog $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input  The validated `zApplyPartnerRequest`.
     */
    public function apply(array $input): Organisation
    {
        return DB::transaction(function () use ($input) {
            $applied = Organisation::query()
                ->whereRaw('lower(licence_number) = ?', [mb_strtolower($input['licence_number'])])
                ->exists();

            if ($applied) {
                throw ApiException::conflict('LICENCE_APPLIED', 'licence_number');
            }

            $this->ensureEmailFree($input['contact_email']);

            $licence = $this->media->claim(null, $input['licence_url'], MediaPurpose::PartnerLicence, 'licence_url');

            $organisation = Organisation::create([
                'kind' => $input['kind'],
                'name' => $input['name'],
                'state' => $input['state'],
                'city' => $input['city'] ?? null,
                'address' => $input['address'],
                'licence_number' => $input['licence_number'],
                'licence_media_id' => $licence->id,
                'contact_name' => $input['contact_name'],
                'contact_phone' => PhoneNumber::normalise($input['contact_phone']),
                'contact_email' => mb_strtolower($input['contact_email']),
                'status' => PartnerStatus::Pending,
                'applied_at' => now(),
            ]);

            PartnerApplied::dispatch($organisation);

            return $organisation;
        });
    }

    /**
     * The team's decision: approve a pending application, suspend an approved partner, reinstate a suspended one.
     */
    public function review(string $id, string $decision, ?string $notes, ?User $by = null): Organisation
    {
        return DB::transaction(function () use ($id, $decision, $notes, $by) {
            $organisation = Organisation::query()
                ->where('uuid', $id)
                ->lockForUpdate()
                ->first();

            if ($organisation === null) {
                throw ApiException::notFound(Organisation::NOT_FOUND);
            }

            match ($decision) {
                'approved' => $this->approve($organisation),
                'suspended' => $this->suspend($organisation, (string) $notes),
                'reinstated' => $this->reinstate($organisation),
            };

            if ($by !== null) {
                $this->audit->record($by, 'partner.reviewed', $organisation, ['decision' => $decision, 'notes' => $notes]);
            }

            return $organisation;
        });
    }

    /**
     * Send an approved partner's contact a new welcome link while they have not set a password.
     */
    public function resendWelcome(string $id, ?User $by = null): Organisation
    {
        return DB::transaction(function () use ($id, $by) {
            $organisation = Organisation::query()
                ->where('uuid', $id)
                ->lockForUpdate()
                ->first();

            if ($organisation === null) {
                throw ApiException::notFound(Organisation::NOT_FOUND);
            }

            if ($organisation->status !== PartnerStatus::Approved) {
                throw ApiException::unprocessable('WELCOME_NOT_APPROVED');
            }

            $account = $organisation
                ->accounts()
                ->whereRaw('lower(email) = ?', [mb_strtolower($organisation->contact_email)])
                ->first();

            if ($account === null) {
                throw ApiException::notFound(Organisation::NOT_FOUND);
            }

            if ($account->password !== null) {
                throw ApiException::unprocessable('WELCOME_ALREADY_USED');
            }

            $this->accounts->welcome($account);

            if ($by !== null) {
                $this->audit->record($by, 'partner.welcome_resent', $organisation);
            }

            return $organisation;
        });
    }

    /**
     * @param  array{kind?: string|null, status?: string|null, q?: string|null}  $filters
     */
    public function list(array $filters, ListQuery $list): Page
    {
        $q = trim((string) ($filters['q'] ?? ''));

        $query = Organisation::query()
            ->with('licence')
            ->when($filters['kind'] ?? null, fn($query, $kind) => $query->where('kind', $kind))
            ->when($filters['status'] ?? null, fn($query, $status) => $query->where('status', $status))
            ->when($q !== '', fn($query) => $query->where(fn($query) => $query
                ->whereLike('name', "%{$q}%")
                ->orWhereLike('licence_number', "%{$q}%")
                ->orWhereLike('state', "%{$q}%")
                ->orWhereLike('contact_name', "%{$q}%")))
            ->orderBy('applied_at')
            ->orderBy('id');

        return $list->paginate($query);
    }

    /**
     * Applications waiting for the team's decision.
     */
    public function awaitingReview(): int
    {
        return Organisation::query()
            ->where('status', PartnerStatus::Pending)
            ->count();
    }

    /**
     * The partner's own account's organisation of a kind; 404 with `$notFound` when it has none.
     */
    public function of(User $account, PartnerKind $kind, string $notFound): Organisation
    {
        $organisation = $account->organisation;

        if ($organisation === null || $organisation->kind !== $kind || $organisation->status === PartnerStatus::Pending) {
            throw ApiException::notFound($notFound);
        }

        return $organisation;
    }

    /**
     * Name, state, city and address, as the partner edits them in Settings.
     *
     * @param  array{name: string, state: string, city: ?string, address: ?string}  $fields
     */
    public function updateListing(Organisation $organisation, array $fields): Organisation
    {
        $organisation->fill([
            'name' => $fields['name'],
            'state' => $fields['state'],
            'city' => $fields['city'] ?? null,
            'address' => $fields['address'] ?? null,
        ]);
        $organisation->save();

        return $organisation;
    }

    /**
     * Make one of the partner's own accounts its owner.
     */
    public function transferOwnership(Organisation $organisation, User $account): Organisation
    {
        $organisation->forceFill(['owner_id' => $account->id])->save();

        return $organisation;
    }

    private function approve(Organisation $organisation): void
    {
        if ($organisation->status !== PartnerStatus::Pending) {
            throw ApiException::unprocessable('PARTNER_NOT_PENDING');
        }

        $this->ensureEmailFree($organisation->contact_email);

        $organisation->fill(['status' => PartnerStatus::Approved, 'notes' => null, 'reviewed_at' => now()]);
        $organisation->save();

        $account = $this->admit($organisation, $organisation->contact_name, $organisation->contact_email, 'contact_email');

        $organisation->forceFill(['owner_id' => $account->id])->save();

        PartnerApproved::dispatch($organisation);
    }

    /**
     * Make a person a member of the organisation: a new account with its first-password link, or
     * their existing verified account, told it was given access. An account already in an
     * organisation is 409 `EMAIL_TAKEN`.
     */
    public function admit(Organisation $organisation, string $name, string $email, string $field): User
    {
        [$account, $linked] = $this->accounts->openOrLink($email, $name, $field);

        if ($account->organisation_id !== null) {
            throw ApiException::conflict('EMAIL_TAKEN', $field);
        }

        $account->organisation_id = $organisation->id;
        $account->email_verified_at ??= now();
        $account->save();
        $account->grantKind($organisation->kind->accountKind());
        $account->setRelation('organisation', $organisation);

        if ($linked) {
            $this->accounts->announceAccess($account, __('identity.places.organisation', ['name' => $organisation->name]));
        } else {
            $this->accounts->welcome($account);
        }

        return $account;
    }

    private function suspend(Organisation $organisation, string $notes): void
    {
        if ($organisation->status !== PartnerStatus::Approved) {
            throw ApiException::unprocessable('PARTNER_NOT_APPROVED');
        }

        $organisation->fill(['status' => PartnerStatus::Suspended, 'notes' => $notes, 'reviewed_at' => now()]);
        $organisation->save();

        PartnerSuspended::dispatch($organisation);

        $this->tell($organisation);
    }

    private function reinstate(Organisation $organisation): void
    {
        if ($organisation->status !== PartnerStatus::Suspended) {
            throw ApiException::unprocessable('PARTNER_NOT_SUSPENDED');
        }

        $organisation->fill(['status' => PartnerStatus::Approved, 'notes' => null, 'reviewed_at' => now()]);
        $organisation->save();

        PartnerReinstated::dispatch($organisation);

        $this->tell($organisation);
    }

    private function tell(Organisation $organisation): void
    {
        $organisation
            ->accounts()
            ->lazyById(50)
            ->each(fn(User $account) => $account->notify(new PartnerStatusChanged($organisation->status, $organisation->notes)));
    }

    /**
     * The contact's address becomes a sign-in, so it may not already be one.
     */
    /**
     * The contact's email may be a verified account outside any organisation, or none.
     */
    private function ensureEmailFree(string $email): void
    {
        $taken = User::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower($email)])
            ->where(fn($query) => $query
                ->whereNull('email_verified_at')
                ->orWhereNotNull('organisation_id'))
            ->exists();

        if ($taken) {
            throw ApiException::conflict('EMAIL_TAKEN', 'contact_email');
        }
    }
}
