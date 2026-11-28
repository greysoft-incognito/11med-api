<?php

namespace App\Models;

use App\Enums\PartnerKind;
use App\Enums\PartnerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A pharmacy or laboratory (portal `zPartner`): its application while pending, and once approved
 * the organisation its portal accounts belong to. Its uuid is the pharmacy's or laboratory's id
 * everywhere the clients see one.
 */
class Organisation extends Model
{
    public const NOT_FOUND = 'PARTNER_NOT_FOUND';

    protected $fillable = [
        'kind',
        'name',
        'state',
        'city',
        'address',
        'licence_number',
        'licence_media_id',
        'contact_name',
        'contact_phone',
        'contact_email',
        'status',
        'notes',
        'applied_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => PartnerKind::class,
            'status' => PartnerStatus::class,
            'applied_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function licence(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'licence_media_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The account that manages the partner's staff.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Approved partners of a kind: the ones the app lists. A suspended one is delisted.
     */
    public function scopeListed(Builder $query, PartnerKind $kind): void
    {
        $query
            ->where('organisations.kind', $kind)
            ->where('organisations.status', PartnerStatus::Approved);
    }

    public function isListed(): bool
    {
        return $this->status === PartnerStatus::Approved;
    }
}
