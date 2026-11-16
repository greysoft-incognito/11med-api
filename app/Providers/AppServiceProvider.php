<?php

namespace App\Providers;

use App\Helpers\Provider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Provider::buildResponseMacros();

        // The public id every domain table carries (App\Models\Concerns\HasPublicId).
        Blueprint::macro('publicId', function () {
            /** @var Blueprint $this */
            return $this->uuid('uuid')->unique();
        });

        // Force https
        if (config('app.env') === 'production' || config('app.force_https')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        Provider::rateLimitCodeRequests();
        Provider::startLogger();
    }
}
