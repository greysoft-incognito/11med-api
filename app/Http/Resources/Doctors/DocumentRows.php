<?php

namespace App\Http\Resources\Doctors;

use App\Enums\DocumentKind;
use App\Models\DoctorProfile;
use App\Services\Media\MediaLibrary;
use Carbon\CarbonInterface;

/**
 * A doctor's documents as both clients list them: all six kinds in the review's order, `missing`
 * where nothing was uploaded. The reviewer's rows also carry the file's signed link.
 */
final class DocumentRows
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function of(DoctorProfile $profile, bool $withUrl = false): array
    {
        return array_map(function (DocumentKind $kind) use ($profile, $withUrl) {
            $document = $profile->document($kind);
            $media = $document?->media;

            $row = [
                'kind' => $kind->value,
                'status' => $document?->status->value ?? 'missing',
                'uploaded_at' => self::iso($document?->uploaded_at),
                'rejection_reason' => $document?->rejection_reason,
            ];

            if ($withUrl) {
                $row = ['kind' => $row['kind'], 'status' => $row['status'], 'url' => $media === null ? null : app(MediaLibrary::class)->url($media)] + $row;
            }

            return $row;
        }, DocumentKind::cases());
    }

    private static function iso(?CarbonInterface $date): ?string
    {
        return $date
            ?->copy()
            ->utc()
            ->toIso8601ZuluString('millisecond');
    }
}
