<?php

namespace App\Services\Media;

use App\Enums\MediaPurpose;
use App\Exceptions\ApiException;
use App\Models\Media;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Uploads and the one rule every record that carries a file keeps: a URL is accepted only
 * from the caller's own upload for that purpose (app handover §12). Records hold the media
 * row, and its URL is made when a response is written, so a signed link is always fresh.
 */
class MediaLibrary
{
    private const UUID = '~[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}~i';

    public function __construct(
        private readonly UploadInspector $inspector,
    ) {}

    /**
     * @param  array{width?: int|null, height?: int|null, size?: int|null}  $claims
     */
    public function upload(?User $user, MediaPurpose $purpose, mixed $file, array $claims): Media
    {
        $owner = $this->owner($user, $purpose);
        $inspected = $this->inspector->inspect($purpose, $file, $claims);
        $uuid = (string) Str::uuid7();
        $disk = $this->diskFor($purpose);
        $path = "media/{$purpose->value}/{$uuid}.{$inspected->extension}";

        Storage::disk($disk)->put($path, $inspected->contents, $purpose->isPrivate() ? 'private' : 'public');

        $media = new Media([
            'user_id' => $owner?->id,
            'purpose' => $purpose,
            'disk' => $disk,
            'path' => $path,
            'name' => $this->name($file->getClientOriginalName(), $inspected->extension),
            'content_type' => $inspected->contentType,
            'width' => $inspected->width,
            'height' => $inspected->height,
            'size' => strlen($inspected->contents),
        ]);

        // The stored file is named by the public id, so the id is fixed before the row exists.
        $media->uuid = $uuid;
        $media->save();

        return $media;
    }

    /**
     * Where the file is fetched from: a public URL for a store picture or a photo, a signed
     * link that lapses for a medical file or a licence.
     */
    public function url(Media $media): string
    {
        if (! $media->purpose->isPrivate()) {
            return Storage::disk($media->disk)->url($media->path);
        }

        return URL::temporarySignedRoute(
            'v1.media.file',
            now()->addMinutes(config('elevenmed.media.signed_minutes')),
            ['media' => $media->uuid]
        );
    }

    /**
     * The file itself, never cached beyond its link's life and never run: a PDF opens sandboxed.
     */
    public function serve(Media $media): StreamedResponse
    {
        $headers = [
            'Content-Type' => $media->content_type,
            'Cache-Control' => 'private, max-age=' . config('elevenmed.media.signed_minutes') * 60,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'; img-src 'self'; style-src 'unsafe-inline'",
        ];

        return Storage::disk($media->disk)->response($media->path, $media->name, $headers, 'inline');
    }

    /**
     * The upload behind a URL, if `$owner` made it for `$purpose`; 422 `MEDIA_NOT_FOUND` on
     * `$field` otherwise. A licence has no owner and may be claimed by one application only.
     */
    public function claim(?User $owner, string $url, MediaPurpose $purpose, string $field): Media
    {
        $media = preg_match(self::UUID, (string) parse_url($url, PHP_URL_PATH), $match) === 1
            ? Media::query()
                ->where('uuid', strtolower($match[0]))
                ->where('purpose', $purpose)
                ->where('user_id', $owner?->id)
                ->first()
            : null;

        if ($media === null || ! $this->identifies($media, $url) || ($owner === null && $media->claimed_at !== null)) {
            throw ApiException::unprocessable(Media::NOT_FOUND, $field);
        }

        $media->claimed_at ??= now();
        $media->save();

        return $media;
    }

    /**
     * A file its record no longer carries, or whose record is gone: removed for real.
     */
    public function discard(Media $media): void
    {
        $disk = $media->disk;
        $path = $media->path;

        $media->forceDelete();

        DB::afterCommit(fn() => Storage::disk($disk)->delete($path));
    }

    /**
     * An erased account's uploads, rows and files (handover §3.1).
     */
    public function forget(User $user): void
    {
        $query = Media::withTrashed()
            ->where('user_id', $user->id);

        $query
            ->clone()
            ->select(['id', 'disk', 'path'])
            ->chunkById(200, function ($chunk) {
                $paths = $chunk
                    ->groupBy('disk')
                    ->map(fn($rows) => $rows
                        ->pluck('path')
                        ->all());

                DB::afterCommit(fn() => $paths->each(fn(array $files, string $disk) => Storage::disk($disk)->delete($files)));
            });

        $query->forceDelete();
    }

    private function owner(?User $user, MediaPurpose $purpose): ?User
    {
        if ($purpose->isPublicUpload()) {
            return null;
        }

        if ($user === null) {
            throw new AuthenticationException;
        }

        if (! in_array($user->role(), $purpose->roles(), true)) {
            throw ApiException::forbidden();
        }

        return $user;
    }

    /**
     * The URL is this file's own address, signed or not; the signature is checked when the file
     * is fetched, not when a record names it.
     */
    private function identifies(Media $media, string $url): bool
    {
        $address = $media->purpose->isPrivate()
            ? route('v1.media.file', ['media' => $media->uuid])
            : Storage::disk($media->disk)->url($media->path);

        return strtok($url, '?') === $address;
    }

    private function diskFor(MediaPurpose $purpose): string
    {
        return config($purpose->isPrivate() ? 'elevenmed.media.private_disk' : 'elevenmed.media.public_disk');
    }

    private function name(string $original, string $extension): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', basename($original)) ?? '';
        $name = Str::limit(trim($name), 150, '');

        return $name === '' ? "upload.{$extension}" : $name;
    }
}
