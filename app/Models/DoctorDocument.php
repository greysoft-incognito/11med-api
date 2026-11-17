<?php

namespace App\Models;

use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One verification document (app `zDoctorDocument`): the upload behind it and the review.
 */
class DoctorDocument extends Model
{
    protected $fillable = [
        'doctor_profile_id',
        'kind',
        'status',
        'media_id',
        'uploaded_at',
        'rejection_reason',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => DocumentKind::class,
            'status' => DocumentStatus::class,
            'uploaded_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(DoctorProfile::class, 'doctor_profile_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
