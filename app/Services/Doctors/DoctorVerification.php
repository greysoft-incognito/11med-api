<?php

namespace App\Services\Doctors;

use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Enums\VerificationStatus;
use App\Events\Doctors\DoctorDocumentReviewed;
use App\Events\Doctors\DoctorReviewed;
use App\Exceptions\ApiException;
use App\Models\DoctorProfile;

/**
 * The verification lifecycle (app spec §4.3, portal handover §6 "Doctors"), the only code that
 * changes a doctor's `verification_status` or a document's review. The caller holds the row lock.
 */
class DoctorVerification
{
    /**
     * Into the queue, or back into it after a rejection; `submitted_at` restarts the 48 hours.
     *
     * @param  list<string>  $missing
     */
    public function submit(DoctorProfile $profile, array $missing): void
    {
        match ($profile->verification_status) {
            VerificationStatus::Approved => throw ApiException::unprocessable('DOCTOR_APPROVED'),
            VerificationStatus::Suspended => throw ApiException::unprocessable('DOCTOR_SUSPENDED'),
            default => null,
        };

        if ($this->isQueued($profile)) {
            return;
        }

        if ($missing !== []) {
            throw ApiException::unprocessable('PROFILE_INCOMPLETE', null, ['missing' => $missing]);
        }

        $profile->verification_status = VerificationStatus::Pending;
        $profile->verification_notes = null;
        $profile->submitted_at = now();
        $profile->save();
    }

    public function isQueued(DoctorProfile $profile): bool
    {
        return $profile->verification_status === VerificationStatus::Pending && $profile->submitted_at !== null;
    }

    public function reviewDocument(DoctorProfile $profile, DocumentKind $kind, bool $accepted, ?string $reason): void
    {
        $document = $profile->document($kind);

        if ($document === null) {
            throw ApiException::unprocessable('DOCUMENT_NOT_UPLOADED', 'kind');
        }

        $document->status = $accepted ? DocumentStatus::Accepted : DocumentStatus::Rejected;
        $document->rejection_reason = $accepted ? null : $reason;
        $document->reviewed_at = now();
        $document->save();

        DoctorDocumentReviewed::dispatch($document);
    }

    /**
     * `approved`, `rejected` and `suspended` with notes, or `reinstated`.
     */
    public function decide(DoctorProfile $profile, string $decision, ?string $notes): void
    {
        $previous = $profile->verification_status;

        $next = match ($decision) {
            'approved' => $this->approve($profile),
            'rejected' => $this->guard($previous !== VerificationStatus::Approved, 'DOCTOR_APPROVED', VerificationStatus::Rejected),
            'suspended' => $this->guard($previous === VerificationStatus::Approved, 'DOCTOR_NOT_APPROVED', VerificationStatus::Suspended),
            'reinstated' => $this->guard($previous === VerificationStatus::Suspended, 'DOCTOR_NOT_SUSPENDED', VerificationStatus::Approved),
        };

        $profile->verification_status = $next;
        $profile->verification_notes = in_array($decision, ['rejected', 'suspended'], true) ? $notes : null;
        $profile->reviewed_at = now();

        // A doctor taken off the directory cannot stay bookable now.
        if ($next !== VerificationStatus::Approved) {
            $profile->is_available_now = false;
        }

        $profile->save();

        DoctorReviewed::dispatch($profile, $previous);
    }

    private function approve(DoctorProfile $profile): VerificationStatus
    {
        if ($profile->verification_status === VerificationStatus::Suspended) {
            throw ApiException::unprocessable('DOCTOR_SUSPENDED');
        }

        $outstanding = array_values(array_filter(
            DocumentKind::required($profile->sub_specialty !== null),
            fn(DocumentKind $kind) => $profile->document($kind)?->status !== DocumentStatus::Accepted
        ));

        if ($outstanding !== []) {
            throw ApiException::unprocessable('DOCUMENTS_OUTSTANDING', null, [
                'kinds' => array_map(fn(DocumentKind $kind) => $kind->value, $outstanding),
            ]);
        }

        return VerificationStatus::Approved;
    }

    private function guard(bool $allowed, string $code, VerificationStatus $next): VerificationStatus
    {
        if (! $allowed) {
            throw ApiException::unprocessable($code);
        }

        return $next;
    }
}
