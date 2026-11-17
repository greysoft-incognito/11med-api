<?php

namespace App\Services\Devices;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Push registrations (app handover §8.1). One install, one account: registering an install
 * another account holds moves it, so the previous account's pushes stop going there.
 */
class Devices
{
    /**
     * @param  array{platform: string, push_provider: string, push_token: string, app_version: string, locale: string}  $data
     */
    public function register(User $user, string $installId, array $data): Device
    {
        return DB::transaction(function () use ($user, $installId, $data) {
            $device = Device::query()
                ->where('install_id', $installId)
                ->lockForUpdate()
                ->first();

            $device ??= new Device(['install_id' => $installId]);

            $device->fill([...$data, 'user_id' => $user->id, 'registered_at' => now()]);
            $device->save();

            return $device;
        });
    }

    /**
     * Only the account holding the install may remove it; anyone else's call does nothing.
     */
    public function unregister(User $user, string $installId): void
    {
        Device::query()
            ->where('install_id', $installId)
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * @param  list<string>  $installIds
     */
    public function forget(User $user, array $installIds): void
    {
        Device::query()
            ->where('user_id', $user->id)
            ->whereIn('install_id', $installIds)
            ->delete();
    }
}
