<?php

namespace App\Services\Consultations;

use App\Enums\ConsultationStatus;
use App\Exceptions\ApiException;
use App\Models\Consultation;
use App\Services\Doctors\DoctorRatings;
use Illuminate\Support\Facades\DB;

/**
 * A patient's rating of a finished consultation (app spec §7.8): once, folded into the doctor's
 * running figures so the directory shows it at once.
 */
class Ratings
{
    public function __construct(
        private readonly DoctorRatings $doctorRatings,
    ) {}

    public function rate(Consultation $consultation, int $stars, ?string $review): void
    {
        DB::transaction(function () use ($consultation, $stars, $review) {
            $current = Consultation::query()
                ->whereKey($consultation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($current->status !== ConsultationStatus::Completed) {
                throw ApiException::unprocessable('CONSULTATION_NOT_COMPLETE');
            }

            if ($current->rated_at !== null) {
                throw ApiException::unprocessable('ALREADY_RATED');
            }

            $current->rating = $stars;
            $current->review = filled($review) ? trim($review) : null;
            $current->rated_at = now();
            $current->save();

            if ($current->doctor_id !== null) {
                $this->doctorRatings->record($current->doctor_id, $stars);
            }
        });
    }
}
