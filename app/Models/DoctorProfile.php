<?php

namespace App\Models;

use App\Enums\DocumentKind;
use App\Enums\Specialty;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A doctor's own record (app `zDoctorProfile`). Bank details and documents never reach a patient:
 * the directory renders its own shape.
 */
class DoctorProfile extends Model
{
    public const NOT_FOUND = 'DOCTOR_NOT_FOUND';

    protected $fillable = [
        'user_id',
        'mdcn_number',
        'specialty',
        'sub_specialty',
        'years_of_experience',
        'current_institution',
        'languages',
        'bio',
        'fee_chat_kobo',
        'fee_audio_kobo',
        'fee_video_kobo',
        'session_minutes',
        'chat_window_hours',
        'follow_up_fee_kobo',
        'bank_name',
        'availability',
        'is_available_now',
    ];

    protected $attributes = [
        'languages' => '[]',
        'availability' => '[]',
        'session_minutes' => 30,
        'chat_window_hours' => 24,
        'is_available_now' => false,
        'verification_status' => 'pending',
        'rating_total' => 0,
        'rating_count' => 0,
        'rating_mean' => 0,
    ];

    protected $hidden = [
        'bank_account_number',
    ];

    protected function casts(): array
    {
        return [
            'specialty' => Specialty::class,
            'years_of_experience' => 'integer',
            'languages' => 'array',
            'fee_chat_kobo' => 'integer',
            'fee_audio_kobo' => 'integer',
            'fee_video_kobo' => 'integer',
            'session_minutes' => 'integer',
            'chat_window_hours' => 'integer',
            'follow_up_fee_kobo' => 'integer',
            'bank_account_number' => 'encrypted',
            'availability' => 'array',
            'is_available_now' => 'boolean',
            'verification_status' => VerificationStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'rating_total' => 'integer',
            'rating_count' => 'integer',
            'rating_mean' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'photo_media_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DoctorDocument::class);
    }

    public function document(DocumentKind $kind): ?DoctorDocument
    {
        return $this->documents->first(fn(DoctorDocument $document) => $document->kind === $kind);
    }

    public function isApproved(): bool
    {
        return $this->verification_status === VerificationStatus::Approved;
    }

    /**
     * The payout account as the doctor recognises it, never in full.
     */
    public function bankAccountMasked(): ?string
    {
        return $this->bank_account_last4 === null ? null : '••••••' . $this->bank_account_last4;
    }

    public function scopeListed(Builder $query): void
    {
        $query
            ->where('verification_status', VerificationStatus::Approved)
            ->whereIn('user_id', User::query()->whereNull('suspended_at')->select('id'));
    }
}
