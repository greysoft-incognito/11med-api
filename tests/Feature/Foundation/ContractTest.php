<?php

namespace Tests\Feature\Foundation;

use App\Services\Contract\RouteInventory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The route ratchet: every client route is registered unless it is still pending,
 * and a registered route must leave the pending list.
 */
class ContractTest extends TestCase
{
    public function test_every_route_not_pending_is_registered(): void
    {
        $inventory = new RouteInventory;
        $pending = $this->normalise($inventory->load(RouteInventory::PENDING_PATH));
        $expected = $this->normalise($inventory->load())->diff($pending);

        $missing = $expected->diff($this->registered());

        $this->assertEmpty($missing->all(), "Contract routes not registered:\n" . $missing->implode("\n"));
    }

    public function test_no_registered_route_is_still_pending(): void
    {
        $inventory = new RouteInventory;
        $pending = $this->normalise($inventory->load(RouteInventory::PENDING_PATH));

        $stale = $pending->intersect($this->registered());

        $this->assertEmpty($stale->all(), "Registered routes still in pending-routes.json:\n" . $stale->implode("\n"));
    }

    public function test_pending_routes_are_all_contract_routes(): void
    {
        $inventory = new RouteInventory;
        $unknown = $inventory->load(RouteInventory::PENDING_PATH)->diff($inventory->load());

        $this->assertEmpty($unknown->all(), "Pending routes the clients do not call:\n" . $unknown->implode("\n"));
    }

    public function test_inventory_matches_the_client_checkouts(): void
    {
        $paths = collect(config('elevenmed.clients'));

        if ($paths->contains(fn(string $path) => ! is_dir($path . '/src/api/mocks'))) {
            $this->markTestSkipped('Client checkouts not present; the committed inventory is used as is.');
        }

        $inventory = new RouteInventory;

        $this->assertSame(
            $inventory->build()->all(),
            $inventory->load()->all(),
            'The clients\' routes changed: run php artisan contract:routes and review the diff.'
        );
    }

    /**
     * @return Collection<int, string>
     */
    private function registered(): Collection
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn($route) => str_starts_with($route->uri(), 'api/v1/'))
            ->flatMap(fn($route) => collect($route->methods())
                ->reject(fn(string $method) => $method === 'HEAD')
                ->map(fn(string $method) => "{$method} {$route->uri()}"));

        return $this->normalise($routes);
    }

    /**
     * Parameter names differ between the mocks and Laravel's bindings; only their places matter.
     *
     * @param  Collection<int, string>  $routes
     * @return Collection<int, string>
     */
    private function normalise(Collection $routes): Collection
    {
        return $routes
            ->map(fn(string $route) => preg_replace('/\{[^}]+\}/', '{}', $route))
            ->unique()
            ->values();
    }
}
