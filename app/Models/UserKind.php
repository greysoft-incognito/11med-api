<?php

namespace App\Models;

use App\Enums\AccountKind;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One kind of account a person holds. Granted when an account opens or is linked, removed with
 * the membership it stands for.
 *
 * @property int $user_id
 * @property AccountKind $kind
 */
class UserKind extends Eloquent
{
    public const UPDATED_AT = null;

    protected $table = 'account_kinds';

    protected $fillable = [
        'user_id',
        'kind',
    ];

    protected function casts(): array
    {
        return [
            'kind' => AccountKind::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
