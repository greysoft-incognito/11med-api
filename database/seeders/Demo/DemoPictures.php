<?php

namespace Database\Seeders\Demo;

use App\Enums\MediaPurpose;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The app mock's stand-in photographs, fetched and stored through the media library as the
 * owner's own claimed upload. A picture that cannot be fetched is left out.
 */
class DemoPictures
{
    public function __construct(
        private readonly MediaLibrary $media,
    ) {}

    public static function unsplash(string $id, int $width, int $height): string
    {
        return "https://images.unsplash.com/photo-{$id}?w={$width}&h={$height}&fit=crop&q=60";
    }

    public static function portrait(string $source): string
    {
        return ctype_digit($source)
            ? "https://images.pexels.com/photos/{$source}/pexels-photo-{$source}.jpeg?auto=compress&cs=tinysrgb&w=400&h=400&fit=crop"
            : "https://images.unsplash.com/photo-{$source}?w=400&h=400&fit=crop&crop=faces&q=60";
    }

    public function store(?User $owner, MediaPurpose $purpose, string $url): ?Media
    {
        $path = tempnam(sys_get_temp_dir(), 'demo');

        try {
            $response = Http::timeout(20)->get($url);

            if (! $response->successful()) {
                return null;
            }

            file_put_contents($path, $response->body());

            [$width, $height] = getimagesize($path) ?: [null, null];

            $file = new UploadedFile($path, 'picture.jpg', null, null, true);
            $media = $this->media->upload($owner, $purpose, $file, ['width' => $width, 'height' => $height]);
            $media->claimed_at = now();
            $media->save();

            return $media;
        } catch (Throwable) {
            return null;
        } finally {
            @unlink($path);
        }
    }
}
