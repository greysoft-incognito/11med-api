<?php

namespace App\Http\Resources\Consultations;

use App\Models\Payout;
use Illuminate\Http\Request;

/**
 * Portal `zAdminPayout`: the app's payout with the doctor, the account it goes to, and the transfer's
 * reference or why it failed.
 *
 * @property Payout $resource
 */
class AdminPayoutResource extends PayoutResource
{
    public function toArray(Request $request): array
    {
        $payout = $this->resource;

        return [
            'id' => $payout->uuid,
            'doctor_id' => $payout->doctor?->uuid,
            'doctor_name' => $payout->doctor_name,
            'bank_name' => $payout->bank_name,
            'bank_account_masked' => $payout->bank_account_masked,
        ] + parent::toArray($request) + [
            'note' => $payout->note,
        ];
    }
}
