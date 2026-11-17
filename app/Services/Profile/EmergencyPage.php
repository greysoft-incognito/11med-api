<?php

namespace App\Services\Profile;

use App\Exceptions\ApiException;
use App\Models\QrAccess;
use App\Models\QrToken;
use Illuminate\Support\Facades\DB;

/**
 * The public emergency page (portal handover §7): no sign-in, the safe subset only, and every
 * open logged against the token, so its owner sees how often and when it was last read.
 */
class EmergencyPage
{
    public function __construct(
        private readonly CurrentMedications $medications,
    ) {}

    public function open(string $secret): EmergencyRecord
    {
        $token = DB::transaction(function () use ($secret) {
            $token = QrToken::query()
                ->where('token_hash', QrToken::hash($secret))
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->first();

            if ($token === null) {
                throw ApiException::notFound(QrToken::NOT_FOUND);
            }

            $token->opened_count++;
            $token->last_opened_at = now();
            $token->save();

            QrAccess::create(['qr_token_id' => $token->id, 'opened_at' => $token->last_opened_at]);

            return $token;
        });

        $patient = $token->user;
        $profile = $patient->medicalProfile()->first();
        $medications = $this->medications->forEmergency($patient);

        return new EmergencyRecord($patient, $profile, $medications, $token);
    }
}
