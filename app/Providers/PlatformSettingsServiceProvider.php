<?php

namespace App\Providers;

use App\Services\Settings\PlatformSettings;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

/**
 * Lays the team's runtime settings over the config for every request, command and queued job.
 */
class PlatformSettingsServiceProvider extends ServiceProvider
{
    public function boot(PlatformSettings $settings): void
    {
        $settings->apply();

        Queue::before(fn() => $settings->apply());
    }
}
