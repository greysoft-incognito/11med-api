<?php

namespace App\Providers;

use App\Enums\MediaPurpose;
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

        // Sign-up and sign-in, per address; the code budgets and lockouts are per number.
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // Portal sign-in and reset emails, per address and email.
        RateLimiter::for('portal-auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip() . '|' . mb_strtolower((string) $request->input('email')));
        });

        // Uploads: a licence per address, since it needs no account; everything else per account.
        RateLimiter::for('media', function (Request $request) {
            $purpose = MediaPurpose::tryFrom((string) $request->input('purpose'));
            $user = $request->user('sanctum');

            return $purpose?->isPublicUpload() || $user === null
                ? Limit::perHour(config('elevenmed.media.public_uploads_per_hour'))->by('media|' . $request->ip())
                : Limit::perHour(config('elevenmed.media.uploads_per_hour'))->by('media|' . $user->id);
        });

        // The emergency page needs no sign-in, so it is limited by token and by address (portal handover §7).
        RateLimiter::for('emergency', function (Request $request) {
            $token = hash('sha256', (string) $request->query('token'));

            return [
                Limit::perHour(config('elevenmed.emergency.opens_per_hour_per_token'))->by('emergency|token|' . $token),
                Limit::perHour(config('elevenmed.emergency.opens_per_hour_per_address'))->by('emergency|ip|' . $request->ip()),
            ];
        });

        // Each invitation is a paid SMS to someone else's phone; each accept attempt guesses a code.
        RateLimiter::for('caregiver-invite', fn(Request $request) => Limit::perDay(config('elevenmed.caregivers.invites_per_day'))->by('caregiver-invite|' . $request->user()?->id));
        RateLimiter::for('caregiver-accept', fn(Request $request) => Limit::perHour(config('elevenmed.caregivers.accept_attempts_per_hour'))->by('caregiver-accept|' . $request->user()?->id));

        // Laboratories
        RateLimiter::for('lab-booking', fn(Request $request) => Limit::perHour(config('elevenmed.laboratories.bookings_per_hour'))->by('lab-booking|' . $request->user()?->id));

        // Partners: the public application, per address, since it needs no account.
        RateLimiter::for('partner-apply', fn(Request $request) => Limit::perHour(config('elevenmed.partners.applications_per_hour'))->by('partner-apply|' . $request->ip()));
        // Payments: each start opens a gateway transaction.
        RateLimiter::for('payments', fn(Request $request) => Limit::perMinute(config('elevenmed.payments.initialise_per_minute'))->by('payments|' . $request->user()?->id));

        Provider::rateLimitCodeRequests();
        Provider::startLogger();
    }
}
