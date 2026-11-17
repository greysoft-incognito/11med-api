<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An emergency QR token (app spec §3.3, `zQrToken`): a long-lived bearer credential to the safe
 * subset of a medical record, found by its hash, replaced at once when revoked.
 */
class QrToken extends Model
{
    public const NOT_FOUND = 'QR_NOT_FOUND';

    protected $fillable = [
        'user_id',
        'token_hash',
        'token',
        'issued_at',
        'opened_count',
        'last_opened_at',
        'revoked_at',
    ];

    protected $hidden = [
        'token',
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'issued_at' => 'datetime',
            'opened_count' => 'integer',
            'last_opened_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accesses(): HasMany
    {
        return $this->hasMany(QrAccess::class);
    }
}
