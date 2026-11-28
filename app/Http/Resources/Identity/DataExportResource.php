<?php

namespace App\Http\Resources\Identity;

use App\Http\Resources\ApiResource;
use App\Models\DataExport;
use App\Services\Identity\Export\DataExports;
use Illuminate\Http\Request;

/**
 * `zDataExport`. A ready export carries a fresh link that lapses in minutes.
 *
 * @property DataExport $resource
 */
class DataExportResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $export = $this->resource;
        $user = $export->user;

        return [
            'id' => $export->uuid,
            'status' => $export->status->value,
            'requested_at' => $this->iso($export->requested_at),
            'ready_at' => $this->iso($export->ready_at),
            'expires_at' => $this->iso($export->expires_at),
            'emailed' => $export->isDownloadable() && $user?->email !== null && $user->email_verified_at !== null,
            'download_url' => app(DataExports::class)->link($export),
        ];
    }
}
