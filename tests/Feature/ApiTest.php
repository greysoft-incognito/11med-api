<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_get_user(): void
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        $response = $this->get('/api/user');
        $response->assertOk();
    }

    /**
     * A basic feature test example.
     */
    public function test_requests_can_be_rate_limited(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $response = $this->get('/api/');
        }
        $response->assertTooManyRequests();
    }
}
