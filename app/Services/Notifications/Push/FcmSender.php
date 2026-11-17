<?php

namespace App\Services\Notifications\Push;

use App\Models\Device;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Cloud Messaging HTTP v1, authorised by the project's service account. Android shows it
 * on the app's `updates` channel (app handover §8.3).
 */
class FcmSender implements PushSender
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function send(Device $device, PushMessage $message): PushOutcome
    {
        $account = $this->account();

        $response = Http::withToken($this->accessToken($account))->post(
            "https://fcm.googleapis.com/v1/projects/{$account['project_id']}/messages:send",
            ['message' => [
                'token' => $device->push_token,
                'notification' => ['title' => $message->title, 'body' => $message->body],
                'data' => $this->data($message),
                'android' => [
                    'priority' => 'high',
                    'notification' => ['channel_id' => config('elevenmed.push.android_channel')],
                ],
            ]],
        );

        if ($response->successful()) {
            return PushOutcome::Delivered;
        }

        $code = (string) ($response->json('error.details.0.errorCode') ?? $response->json('error.status'));

        if ($this->tokenRefused($response->status(), $code, (string) $response->json('error.message'))) {
            return PushOutcome::InvalidToken;
        }

        Log::warning('push.fcm.failed', ['status' => $response->status(), 'code' => $code, 'install_id' => $device->install_id]);

        return PushOutcome::Failed;
    }

    /**
     * An unregistered token, or a malformed one; any other invalid argument is a fault in the message.
     */
    private function tokenRefused(int $status, string $code, string $message): bool
    {
        return $status === 404
            || $code === 'UNREGISTERED'
            || ($code === 'INVALID_ARGUMENT' && str_contains($message, 'registration token'));
    }

    /**
     * FCM data values are strings, so a null `target_id` is left out, and the whole payload also
     * rides as JSON under `body`, where expo-notifications reads a remote message's data.
     *
     * @return array<string, string>
     */
    private function data(PushMessage $message): array
    {
        $payload = $message->payload();
        $strings = array_map(fn($value) => (string) $value, array_filter($payload, fn($value) => $value !== null));

        return [...$strings, 'body' => json_encode($payload, JSON_THROW_ON_ERROR)];
    }

    /**
     * @return array{project_id: string, client_email: string, private_key: string}
     */
    private function account(): array
    {
        return json_decode(file_get_contents(config('elevenmed.push.fcm.credentials_path')), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array{project_id: string, client_email: string, private_key: string}  $account
     */
    private function accessToken(array $account): string
    {
        return Cache::remember('push.fcm.token', now()->addMinutes(50), function () use ($account) {
            $now = time();

            $assertion = JWT::encode([
                'iss' => $account['client_email'],
                'scope' => self::SCOPE,
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], $account['private_key'], 'RS256');

            return Http::asForm()
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ])
                ->throw()
                ->json('access_token');
        });
    }
}
