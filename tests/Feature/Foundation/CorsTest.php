<?php

namespace Tests\Feature\Foundation;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_the_portals_origin_may_call_the_api(): void
    {
        config(['cors.allowed_origins' => ['https://portal.example.ng']]);

        $this->withHeaders([
            'Origin' => 'https://portal.example.ng',
            'Access-Control-Request-Method' => 'POST',
        ])
            ->options('api/v1/portal/auth/sign-in')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://portal.example.ng')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    public function test_another_origin_is_not_allowed(): void
    {
        config(['cors.allowed_origins' => ['https://portal.example.ng']]);

        $origin = $this->withHeaders(['Origin' => 'https://elsewhere.example'])
            ->getJson('api/v1/health')
            ->assertOk()
            ->headers
            ->get('Access-Control-Allow-Origin');

        $this->assertNotSame('https://elsewhere.example', $origin);
        $this->assertNotSame('*', $origin);
    }
}
