<?php

namespace App\Models;

use App\Enums\ConsultationStatus;
use App\Enums\PaymentPurpose;
use App\Enums\SessionType;
use App\Enums\Specialty;
use App\Exceptions\ApiException;
use App\Services\Payments\Payable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A consultation (app `zConsultation`). Only `ConsultationLifecycle` changes its status. The
 * complaint and the chat are medical data: never logged.
 */
class Consultation extends Model implements Payable
{
    public const NOT_FOUND = 'CONSULTATION_NOT_FOUND';

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'doctor_name',
        'doctor_specialty',
        'session_type',
        'status',
        'complaint',
        'fee_kobo',
        'scheduled_for',
        'scheduled',
        'length_minutes',
        'follow_up_of_id',
        'expires_at',
        'paid_at',
        'started_at',
        'ended_at',
        'profile_shared',
    ];

    protected function casts(): array
    {
        return [
            'doctor_specialty' => Specialty::class,
            'session_type' => SessionType::class,
            'status' => ConsultationStatus::class,
            'fee_kobo' => 'integer',
            'scheduled_for' => 'datetime',
            'scheduled' => 'boolean',
            'length_minutes' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'profile_shared' => 'boolean',
            'rating' => 'integer',
            'rated_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function followUpOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'follow_up_of_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConsultationMessage::class);
    }

    public function prescription(): HasOne
    {
        return $this->hasOne(Prescription::class);
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function paymentPurpose(): PaymentPurpose
    {
        return PaymentPurpose::Consultation;
    }

    public function paymentTargetId(): string
    {
        return $this->uuid;
    }

    public function paymentReference(): string
    {
        return $this->uuid;
    }

    public function paymentPartnerName(): ?string
    {
        return null;
    }

    public function payer(): User
    {
        return $this->patient;
    }

    public function amountKobo(): int
    {
        return $this->fee_kobo;
    }

    public function assertPayable(): void
    {
        if ($this->isPaid()) {
            throw ApiException::unprocessable('ALREADY_PAID');
        }

        if ($this->status !== ConsultationStatus::Pending) {
            throw ApiException::unprocessable('CONSULTATION_CLOSED');
        }
    }
}
