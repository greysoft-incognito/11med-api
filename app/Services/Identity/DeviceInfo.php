<?php

namespace App\Services\Identity;

use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * What a sign-in came from, as the person would recognise it: "iPhone, iOS 18" or "Chrome on macOS".
 * The app names itself in `X-Device-Name` and `X-Device-Platform`; a browser is read from its user agent.
 * `key` tells a device seen before from a new one.
 */
final readonly class DeviceInfo
{
    public function __construct(
        public ?string $name,
        public ?string $platform,
        public ?string $key,
    ) {}

    public static function fromRequest(Request $request, ?string $installId = null): self
    {
        $platform = Str::lower((string) $request->header('X-Device-Platform'));
        $platform = in_array($platform, ['ios', 'android', 'web'], true) ? $platform : null;
        $name = self::clean($request->header('X-Device-Name'));
        $agent = (string) $request->userAgent();

        if ($name === null && $agent !== '') {
            [$name, $detected] = self::describe($agent);
            $platform ??= $detected;
        }

        $key = match (true) {
            $installId !== null => hash('sha256', 'install:' . $installId),
            $agent !== '' => hash('sha256', 'agent:' . $agent),
            default => null,
        };

        return new self($name, $platform, $key);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private static function describe(string $agent): array
    {
        $detector = new DeviceDetector($agent);
        $detector->discardBotInformation();
        $detector->parse();

        $client = $detector->getClient('name');
        $os = $detector->getOs('name');
        $client = is_string($client) && $client !== '' && $client !== 'UNK' ? $client : null;
        $os = is_string($os) && $os !== '' && $os !== 'UNK' ? $os : null;

        $name = match (true) {
            $client !== null && $os !== null => "{$client} on {$os}",
            default => $client ?? $os,
        };

        $platform = match ($os) {
            'iOS', 'iPadOS' => $detector->isBrowser() ? 'web' : 'ios',
            'Android' => $detector->isBrowser() ? 'web' : 'android',
            null => null,
            default => 'web',
        };

        return [self::clean($name), $platform];
    }

    private static function clean(?string $value): ?string
    {
        $value = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $value) ?? '');

        return $value === '' ? null : Str::limit($value, 117);
    }
}
