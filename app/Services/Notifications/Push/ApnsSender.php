<?php

namespace App\Services\Notifications\Push;

use App\Models\Device;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Apple Push Notification service over HTTP/2, signed with the team's `.p8` key. A token Apple
 * reports unregistered or malformed is invalid. The payload rides at the top level and again under
 * `body`, where expo-notifications reads a remote notification's data.
 */
class ApnsSender implements PushSender
{
    private const INVALID_REASONS = ['BadDeviceToken', 'Unregistered', 'DeviceTokenNotForTopic'];

    public function send(Device $device, PushMessage $message): PushOutcome
    {
        $config = config('elevenmed.push.apns');
        $host = $config['production'] ? 'https://api.push.apple.com' : 'https://api.sandbox.push.apple.com';

        $response = Http::withOptions(['version' => 2.0])
            ->withHeaders([
                'authorization' => 'bearer ' . $this->token(),
                'apns-topic' => $config['bundle_id'],
                'apns-push-type' => 'alert',
                'apns-priority' => '10',
            ])
            ->post($host . '/3/device/' . $device->push_token, [
                'aps' => [
                    'alert' => ['title' => $message->title, 'body' => $message->body],
                    'sound' => 'default',
                ],
                ...$message->payload(),
                'body' => $message->payload(),
            ]);

        if ($response->successful()) {
            return PushOutcome::Delivered;
        }

        $reason = (string) $response->json('reason');

        if ($response->status() === 410 || in_array($reason, self::INVALID_REASONS, true)) {
            return PushOutcome::InvalidToken;
        }

        Log::warning('push.apns.failed', ['status' => $response->status(), 'reason' => $reason, 'install_id' => $device->install_id]);

        return PushOutcome::Failed;
    }

    /**
     * Apple accepts a provider token for an hour and refuses one refreshed more than every twenty minutes.
     */
    private function token(): string
    {
        return Cache::remember('push.apns.token', now()->addMinutes(40), function () {
            $config = config('elevenmed.push.apns');
            $key = file_get_contents($config['key_path']);

            return JWT::encode(['iss' => $config['team_id'], 'iat' => time()], $key, 'ES256', $config['key_id']);
        });
    }
}
