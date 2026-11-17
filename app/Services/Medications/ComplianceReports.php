<?php

namespace App\Services\Medications;

use App\Exceptions\ApiException;
use App\Models\DoseLog;
use App\Models\Medication;
use App\Models\User;
use App\Services\Subscriptions\Entitlements;
use App\Support\LocalDay;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The compliance report (app handover §9): a PDF rendered here, so a clinician reads the same
 * document whichever phone asked, behind a link that lapses in minutes. Premium, owner only.
 */
class ComplianceReports
{
    private const DIRECTORY = 'compliance-reports';

    private const MEDICATIONS_SHOWN = 50;

    public function __construct(
        private readonly Entitlements $entitlements,
        private readonly Compliance $compliance,
    ) {}

    /**
     * @return array{url: string, expires_at: CarbonImmutable, window_days: int, generated_at: CarbonImmutable}
     */
    public function generate(User $patient, int $windowDays): array
    {
        $this->entitlements->requirePremium($patient);

        $now = CarbonImmutable::now();
        $today = LocalDay::today();
        $id = (string) Str::uuid7($now);
        $expires = $now->addMinutes(config('elevenmed.medications.report_minutes'));

        $pdf = Pdf::loadView('reports.compliance', [
            'patient' => $patient,
            'compliance' => $this->compliance->for($patient, $today, $windowDays),
            'skips' => $this->skipReasons($patient, $today, $windowDays),
            'medications' => $this->current($patient, $today),
            'from' => $today->subDays($windowDays - 1)->toDateString(),
            'to' => $today->toDateString(),
            'generated' => $now->setTimezone(LocalDay::zone())->format('Y-m-d H:i'),
        ]);

        Storage::disk($this->disk())->put($this->path($id), $pdf->output());

        return [
            'url' => URL::temporarySignedRoute('v1.medications.report-file', $expires, ['report' => $id]),
            'expires_at' => $expires,
            'window_days' => $windowDays,
            'generated_at' => $now,
        ];
    }

    public function serve(string $id): StreamedResponse
    {
        $path = $this->path($id);

        if (! Str::isUuid($id) || ! Storage::disk($this->disk())->exists($path)) {
            throw ApiException::forbidden('LINK_EXPIRED');
        }

        return Storage::disk($this->disk())->response($path, '11med-compliance-report.pdf', [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    /**
     * Removes report files older than `report_keep_hours`, aged by the time in their uuid7 name;
     * their links lapsed long before.
     */
    public function prune(CarbonImmutable $now): int
    {
        $disk = Storage::disk($this->disk());
        $before = $now->subHours(config('elevenmed.medications.report_keep_hours'))->getTimestampMs();
        $removed = 0;

        foreach ($disk->files(self::DIRECTORY) as $file) {
            $created = hexdec(substr(str_replace('-', '', basename($file, '.pdf')), 0, 12));

            if ($created < $before) {
                $disk->delete($file);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Skips by reason over the window, counted in SQL.
     *
     * @return array<string, int>
     */
    private function skipReasons(User $patient, CarbonImmutable $today, int $windowDays): array
    {
        return DoseLog::query()
            ->whereHas('medication')
            ->where('user_id', $patient->id)
            ->where('state', 'skipped')
            ->where('scheduled_for', '>=', $today->subDays($windowDays - 1)->utc())
            ->where('scheduled_for', '<', $today->addDay()->utc())
            ->selectRaw("COALESCE(skip_reason, 'other') AS reason, COUNT(*) AS skips")
            ->groupBy('reason')
            ->orderBy('reason')
            ->toBase()
            ->pluck('skips', 'reason')
            ->map(fn($count) => (int) $count)
            ->all();
    }

    /**
     * @return Collection<int, Medication>
     */
    private function current(User $patient, CarbonImmutable $today)
    {
        return Medication::query()
            ->where('user_id', $patient->id)
            ->active($today)
            ->orderBy('drug_name')
            ->limit(self::MEDICATIONS_SHOWN)
            ->get();
    }

    private function disk(): string
    {
        return config('elevenmed.medications.report_disk');
    }

    private function path(string $id): string
    {
        return self::DIRECTORY . '/' . $id . '.pdf';
    }
}
