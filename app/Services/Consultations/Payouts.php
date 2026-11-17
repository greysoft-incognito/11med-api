<?php

namespace App\Services\Consultations;

use App\Enums\ConsultationStatus;
use App\Enums\PayoutStatus;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\Consultation;
use App\Models\DoctorProfile;
use App\Models\Payout;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Doctors' payouts (app spec §7.7, portal handover §6 "Payouts"): made every 48 hours, one per doctor,
 * from completed, paid consultations not yet in a payout; settled by the team with the transfer's
 * reference, or failed with why, in which case the consultations return to the next payout.
 */
class Payouts
{
    /**
     * @return int the payouts made
     */
    public function run(CarbonImmutable $periodEnd): int
    {
        $made = 0;

        $this
            ->owed($periodEnd)
            ->select('doctor_id')
            ->distinct()
            ->orderBy('doctor_id')
            ->chunk(200, function ($doctors) use ($periodEnd, &$made) {
                foreach ($doctors as $row) {
                    $made += $this->make((int) $row->doctor_id, $periodEnd) ? 1 : 0;
                }
            });

        return $made;
    }

    public function list(?PayoutStatus $status, ListQuery $list): Page
    {
        $query = Payout::query()
            ->when($status !== null, fn($q) => $q->where('status', $status))
            ->orderBy('status_rank')
            ->orderByDesc('period_end')
            ->orderByDesc('id');

        return $list->paginate($query);
    }

    /**
     * @param  array{decision: string, reference?: string, reason?: string}  $data
     */
    public function settle(string $id, array $data): Payout
    {
        return DB::transaction(function () use ($id, $data) {
            $payout = Payout::query()
                ->where('uuid', $id)
                ->lockForUpdate()
                ->first();

            if ($payout === null) {
                throw ApiException::notFound(Payout::NOT_FOUND);
            }

            if ($payout->status !== PayoutStatus::Pending) {
                throw ApiException::unprocessable('PAYOUT_SETTLED');
            }

            if ($data['decision'] === PayoutStatus::Paid->value) {
                $payout->status = PayoutStatus::Paid;
                $payout->paid_at = now();
                $payout->note = trim($data['reference']);
            } else {
                $payout->status = PayoutStatus::Failed;
                $payout->note = trim($data['reason']);

                Consultation::query()
                    ->where('payout_id', $payout->id)
                    ->update(['payout_id' => null]);
            }

            $payout->save();

            return $payout;
        });
    }

    private function make(int $doctorId, CarbonImmutable $periodEnd): bool
    {
        return DB::transaction(function () use ($doctorId, $periodEnd) {
            $doctor = DoctorProfile::query()
                ->with('user:id,full_name')
                ->where('user_id', $doctorId)
                ->lockForUpdate()
                ->first();

            $owed = $this
                ->owed($periodEnd)
                ->where('doctor_id', $doctorId);

            $periodStart = $owed
                ->clone()
                ->min('ended_at');

            if ($doctor === null || $periodStart === null) {
                return false;
            }

            $payout = Payout::create([
                'doctor_id' => $doctorId,
                'doctor_name' => $doctor->user->full_name,
                'bank_name' => $doctor->bank_name,
                'bank_account_masked' => $doctor->bankAccountMasked(),
                'commission_percent' => config('elevenmed.consultations.commission_percent'),
                'status' => PayoutStatus::Pending,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ]);

            $owed->update(['payout_id' => $payout->id]);

            $totals = Consultation::query()
                ->where('payout_id', $payout->id)
                ->selectRaw('coalesce(sum(fee_kobo), 0) as gross, count(*) as consultations')
                ->first();

            $gross = (int) $totals->gross;
            $commission = $this->commission($gross, $payout->commission_percent);

            $payout->fill([
                'gross_kobo' => $gross,
                'commission_kobo' => $commission,
                'net_kobo' => $gross - $commission,
                'consultation_count' => (int) $totals->consultations,
            ]);
            $payout->save();

            return true;
        });
    }

    public function commission(int $gross, float $percent): int
    {
        return (int) round($gross * $percent / 100);
    }

    /**
     * Completed, paid consultations not yet in a payout, ended by `$periodEnd`.
     */
    public function owed(?CarbonImmutable $periodEnd = null): Builder
    {
        return Consultation::query()
            ->where('status', ConsultationStatus::Completed)
            ->whereNotNull('paid_at')
            ->whereNotNull('doctor_id')
            ->whereNull('payout_id')
            ->when($periodEnd !== null, fn($q) => $q->where('ended_at', '<=', $periodEnd));
    }
}
