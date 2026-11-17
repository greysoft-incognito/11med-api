<?php

namespace App\Services\Identity;

use App\Exceptions\ApiException;
use App\Models\OtpCode;
use Illuminate\Support\Facades\DB;
use Valorin\Random\Random;

/**
 * Six-digit codes for a target (`phone:080…`, `email:<user id>`): one live code per target,
 * ten minutes, five tries, and three resends in any thirty minutes.
 */
class OneTimeCodes
{
    public function issue(string $target, bool $resend = false): CodeSent
    {
        $code = $this->newCode();

        DB::transaction(function () use ($target, $code, $resend) {
            OtpCode::query()
                ->where('target', $target)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            OtpCode::create([
                'target' => $target,
                'code_hash' => hash('sha256', $code),
                'resend' => $resend,
                'expires_at' => now()->addSeconds(config('elevenmed.otp.ttl_seconds')),
            ]);
        });

        return new CodeSent($code, config('elevenmed.otp.ttl_seconds'), $this->resendsRemaining($target));
    }

    /**
     * Issue another code within the resend budget, or refuse with the wait.
     */
    public function resend(string $target, string $limitCode = 'AUTH_OTP_RESEND_LIMIT'): CodeSent
    {
        $window = now()->subMinutes(config('elevenmed.otp.resend_window_minutes'));

        $recent = OtpCode::query()
            ->where('target', $target)
            ->where('resend', true)
            ->where('created_at', '>', $window)
            ->orderBy('created_at');

        if ($recent->count() >= config('elevenmed.otp.max_resends')) {
            $oldest = $recent->value('created_at');
            $wait = (int) now()->diffInMilliseconds($oldest->copy()->addMinutes(config('elevenmed.otp.resend_window_minutes')));

            throw ApiException::tooManyRequests($limitCode, max($wait, 1000));
        }

        return $this->issue($target, true);
    }

    /**
     * Spend the live code, or refuse with `field: code`.
     */
    public function consume(string $target, string $code): void
    {
        $refusal = DB::transaction(function () use ($target, $code) {
            $row = OtpCode::query()
                ->where('target', $target)
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                return 'AUTH_INVALID_OTP';
            }

            if ($row->expires_at->isPast()) {
                return 'AUTH_OTP_EXPIRED';
            }

            if (! hash_equals($row->code_hash, hash('sha256', $code))) {
                $row->attempts++;
                $row->consumed_at = $row->attempts >= config('elevenmed.otp.max_attempts') ? now() : null;
                $row->save();

                return 'AUTH_INVALID_OTP';
            }

            $row->update(['consumed_at' => now()]);

            return null;
        });

        if ($refusal !== null) {
            throw ApiException::badRequest($refusal, 'code', ApiException::messageFor($refusal));
        }
    }

    private function resendsRemaining(string $target): int
    {
        $used = OtpCode::query()
            ->where('target', $target)
            ->where('resend', true)
            ->where('created_at', '>', now()->subMinutes(config('elevenmed.otp.resend_window_minutes')))
            ->count();

        return max(0, config('elevenmed.otp.max_resends') - $used);
    }

    private function newCode(): string
    {
        $fixed = config('elevenmed.otp.fixed_code');

        if (is_string($fixed) && preg_match('/^\d{6}$/', $fixed) === 1 && ! app()->isProduction()) {
            return $fixed;
        }

        return Random::otp(6);
    }
}
