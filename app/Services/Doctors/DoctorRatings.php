<?php

namespace App\Services\Doctors;

use App\Models\DoctorProfile;
use Illuminate\Support\Facades\DB;

/**
 * A doctor's running rating, kept on the profile so the directory sorts by it in SQL. The
 * Consultations module records each patient's rating here as it is given.
 */
class DoctorRatings
{
    public function record(int $doctorUserId, int $stars): void
    {
        $stars = max(1, min(5, $stars));

        DoctorProfile::query()
            ->where('user_id', $doctorUserId)
            ->update([
                'rating_total' => DB::raw("rating_total + {$stars}"),
                'rating_count' => DB::raw('rating_count + 1'),
                'rating_mean' => DB::raw("round((rating_total + {$stars})::numeric / (rating_count + 1), 2)"),
            ]);
    }
}
