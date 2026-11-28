<?php

namespace Tests\Feature\Staff;

use App\Enums\AdminPermission;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PermissionGatesTest extends TestCase
{
    public function test_every_admin_portal_route_names_a_catalogue_permission(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn($route) => str_starts_with($route->uri(), 'api/v1/portal/admin/'));

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $gates = collect($route->gatherMiddleware())
                ->filter(fn($middleware) => is_string($middleware) && str_starts_with($middleware, 'permission:'))
                ->flatMap(fn($middleware) => explode('|', substr($middleware, strlen('permission:'))));

            $this->assertNotEmpty($gates, "{$route->uri()} has no permission");
            $gates->each(fn($name) => $this->assertNotNull(AdminPermission::tryFrom($name), "{$route->uri()}: {$name}"));
        }
    }

    public function test_an_admin_without_the_permission_is_refused_and_with_it_is_let_in(): void
    {
        $finance = User::factory()
            ->staff([AdminPermission::PayoutsView, AdminPermission::SubscriptionsView])
            ->create();

        $this->actingAs($finance)->getJson('api/v1/portal/admin/payouts')->assertOk();
        $this->actingAs($finance)->getJson('api/v1/portal/admin/subscriptions')->assertOk();
        $this->actingAs($finance)->getJson('api/v1/portal/admin/doctors')->assertForbidden();
        $this->actingAs($finance)->getJson('api/v1/portal/admin/partners')->assertForbidden();
        $this->actingAs($finance)->getJson('api/v1/portal/admin/summary')->assertForbidden();
        $this->actingAs($finance)
            ->postJson('api/v1/portal/admin/subscriptions', ['phone' => '08031112222', 'premium_until' => '2027-01-01', 'reference' => 'PSK-1'])
            ->assertForbidden();
    }
}
