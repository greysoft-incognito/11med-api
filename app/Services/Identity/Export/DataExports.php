<?php

namespace App\Services\Identity\Export;

use App\Enums\DataExportStatus;
use App\Exceptions\ApiException;
use App\Jobs\Identity\BuildDataExport;
use App\Models\DataExport;
use App\Models\User;
use App\Notifications\Identity\DataExportReady;
use Carbon\CarbonInterface;
use Illuminate\Http\File as LocalFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipArchive;

/**
 * Download my data (NDPR right of access): a zip of `data.json` (every record, as stored) and
 * `summary.html` (what it holds, readable), built by a job, kept on a private disk for two days.
 */
class DataExports
{
    private const DIRECTORY = 'data-exports';

    /**
     * Ask for a copy. One a day; a failed build does not count.
     */
    public function request(User $user): DataExport
    {
        $since = now()->subHours(config('elevenmed.data_export.every_hours'));

        $last = DataExport::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', DataExportStatus::Failed)
            ->where('requested_at', '>', $since)
            ->latest('requested_at')
            ->first();

        if ($last !== null) {
            $wait = (int) now()->diffInMilliseconds($last->requested_at->copy()->addHours(config('elevenmed.data_export.every_hours')));

            throw ApiException::tooManyRequests('DATA_EXPORT_LIMIT', max($wait, 1000));
        }

        $export = DataExport::create([
            'user_id' => $user->id,
            'status' => DataExportStatus::Pending,
            'requested_at' => now(),
        ]);

        BuildDataExport::dispatch($export->id)->afterCommit();

        return $export;
    }

    public function latest(User $user): ?DataExport
    {
        return DataExport::query()
            ->where('user_id', $user->id)
            ->latest('requested_at')
            ->latest('id')
            ->first();
    }

    public function build(DataExport $export): void
    {
        $user = $export->user;
        $work = storage_path('app/tmp/' . $export->uuid);

        File::ensureDirectoryExists($work);

        try {
            $sections = $this->write($user, $work);
            $zip = new LocalFile($this->zip($work, $user, $sections));
            $path = Storage::disk($this->disk())->putFileAs(self::DIRECTORY, $zip, $export->uuid . '.zip');

            $export->update([
                'status' => DataExportStatus::Ready,
                'path' => $path,
                'ready_at' => now(),
                'expires_at' => now()->addHours(config('elevenmed.data_export.keep_hours')),
            ]);

            if ($user->email !== null && $user->email_verified_at !== null) {
                $user->notify(new DataExportReady($this->link($export, $export->expires_at), $export->expires_at));
            }
        } catch (Throwable $e) {
            $export->update(['status' => DataExportStatus::Failed]);

            throw $e;
        } finally {
            File::deleteDirectory($work);
        }
    }

    /**
     * A signed link to the file, lapsing at the given time or with the export, whichever is sooner.
     */
    public function link(DataExport $export, ?CarbonInterface $until = null): ?string
    {
        if (! $export->isDownloadable()) {
            return null;
        }

        $until ??= now()->addMinutes(config('elevenmed.data_export.link_minutes'));
        $until = $until->min($export->expires_at);

        return URL::temporarySignedRoute('v1.data-exports.file', $until, ['export' => $export->uuid]);
    }

    public function serve(string $id): StreamedResponse
    {
        $export = Str::isUuid($id) ? DataExport::where('uuid', $id)->first() : null;

        if ($export === null || ! $export->isDownloadable() || ! Storage::disk($this->disk())->exists($export->path)) {
            throw ApiException::forbidden('LINK_EXPIRED');
        }

        return Storage::disk($this->disk())->download($export->path, '11med-my-data.zip', [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array<string, array{title: string, rows: int}>
     */
    private function write(User $user, string $work): array
    {
        $writer = new PersonalDataWriter($work . '/data.json', now());

        foreach (app()->tagged('personal-data') as $module) {
            $module->export($user, $writer);
        }

        return $writer->finish();
    }

    /**
     * @param  array<string, array{title: string, rows: int}>  $sections
     */
    private function zip(string $work, User $user, array $sections): string
    {
        $summary = view('exports.personal-data', [
            'user' => $user,
            'sections' => $sections,
            'exported' => now()->setTimezone(config('elevenmed.timezone'))->format('j F Y, g:i a'),
        ])->render();

        $path = $work . '/export.zip';
        $zip = new ZipArchive;

        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($work . '/data.json', 'data.json');
        $zip->addFromString('summary.html', $summary);
        $zip->close();

        return $path;
    }

    private function disk(): string
    {
        return config('elevenmed.data_export.disk');
    }
}
