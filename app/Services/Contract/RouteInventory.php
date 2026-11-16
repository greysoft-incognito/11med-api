<?php

namespace App\Services\Contract;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * The routes the 11med clients call, read from the `route(...)` registrations of their mocks.
 * Entries are `METHOD path` with Laravel's `{param}` placeholders, e.g. `GET api/v1/labs/{labId}`.
 */
final class RouteInventory
{
    public const PATH = 'tests/Contract/routes.json';

    public const PENDING_PATH = 'tests/Contract/pending-routes.json';

    /**
     * Scan one client checkout.
     *
     * @return Collection<int, string>
     */
    public function scan(string $clientPath): Collection
    {
        $mocks = $clientPath . '/src/api/mocks';

        if (! is_dir($mocks)) {
            throw new RuntimeException("No mocks at {$mocks}");
        }

        return collect(File::allFiles($mocks))
            ->filter(fn($file) => $file->getExtension() === 'ts')
            ->flatMap(fn($file) => $this->routesIn($file->getContents()))
            ->unique()
            ->values();
    }

    /**
     * Both clients' routes less the mock-only ones, sorted.
     *
     * @return Collection<int, string>
     */
    public function build(): Collection
    {
        $mockOnly = config('elevenmed.mock_only_routes');

        return collect(config('elevenmed.clients'))
            ->flatMap(fn(string $path) => $this->scan($path))
            ->unique()
            ->reject(fn(string $route) => in_array($route, $mockOnly, true))
            ->sort()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public function load(string $path = self::PATH): Collection
    {
        return collect(json_decode(File::get(base_path($path)), true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * @param  Collection<int, string>  $routes
     */
    public function write(Collection $routes, string $path = self::PATH): void
    {
        File::put(base_path($path), $routes->values()->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    /**
     * @return list<string>
     */
    private function routesIn(string $source): array
    {
        $base = preg_match("/const BASE = '([^']+)'/", $source, $m) === 1 ? $m[1] : null;

        preg_match_all("/route\\(\\s*'(GET|POST|PUT|PATCH|DELETE)',\\s*('[^']+'|`[^`]+`|BASE)/", $source, $matches, PREG_SET_ORDER);

        return array_map(function (array $match) use ($base): string {
            $path = $match[2] === 'BASE' ? (string) $base : str_replace('${BASE}', (string) $base, trim($match[2], "'`"));
            $path = preg_replace('/:(\w+)/', '{$1}', ltrim($path, '/'));

            return "{$match[1]} {$path}";
        }, $matches);
    }
}
