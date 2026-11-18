<?php

namespace Tests\Feature\Identity;

use App\Enums\AuthClient;
use App\Events\Identity\SessionsEnded;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Identity\SessionTokens;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SessionTokensTest extends TestCase
{
    private SessionTokens $tokens;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum'])->get('api/v1/_probe', fn() => ['ok' => true]);

        $this->tokens = app(SessionTokens::class);
        $this->user = User::factory()
            ->app()
            ->create();
    }

    public function test_an_access_token_works_for_fifteen_minutes_then_says_token_expired(): void
    {
        $pair = $this->tokens->issue($this->user, AuthClient::App);

        $this->withToken($pair->accessToken)
            ->getJson('api/v1/_probe')
            ->assertOk();

        $this->travel(16)->minutes();
        $this->app['auth']->forgetGuards();

        $this->withToken($pair->accessToken)
            ->getJson('api/v1/_probe')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'TOKEN_EXPIRED');
    }

    public function test_a_bad_token_is_unauthenticated(): void
    {
        $this->withToken('1|nonsense')
            ->getJson('api/v1/_probe')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_a_request_that_does_not_ask_for_json_is_still_answered_with_a_401(): void
    {
        $pair = $this->tokens->issue($this->user, AuthClient::App);

        $this->get('api/v1/_probe')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->travel(16)->minutes();

        $this->withToken($pair->accessToken)
            ->get('api/v1/_probe')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'TOKEN_EXPIRED');
    }

    public function test_rotation_kills_the_old_pair_and_keeps_the_family(): void
    {
        $first = $this->tokens->issue($this->user, AuthClient::App, 'c1a5e2a8-1111-4a4a-8a8a-000000000001');

        [$user, $second] = $this->tokens->rotate($first->refreshToken, AuthClient::App);

        $this->assertTrue($user->is($this->user));
        $this->assertSame($first->family, $second->family);
        $this->assertNotSame($first->refreshToken, $second->refreshToken);

        $this->withToken($first->accessToken)
            ->getJson('api/v1/_probe')
            ->assertUnauthorized();

        $this->app['auth']->forgetGuards();

        $this->withToken($second->accessToken)
            ->getJson('api/v1/_probe')
            ->assertOk();
    }

    public function test_reusing_a_spent_refresh_token_ends_the_family(): void
    {
        Event::fake([SessionsEnded::class]);

        $first = $this->tokens->issue($this->user, AuthClient::App, 'c1a5e2a8-1111-4a4a-8a8a-000000000001');
        [, $second] = $this->tokens->rotate($first->refreshToken, AuthClient::App);

        $this->assertRefused(fn() => $this->tokens->rotate($first->refreshToken, AuthClient::App), 'AUTH_SESSION_EXPIRED');
        $this->assertRefused(fn() => $this->tokens->rotate($second->refreshToken, AuthClient::App), 'AUTH_SESSION_EXPIRED');

        $this->withToken($second->accessToken)
            ->getJson('api/v1/_probe')
            ->assertUnauthorized();

        Event::assertDispatched(
            SessionsEnded::class,
            fn(SessionsEnded $e) => $e->installIds === ['c1a5e2a8-1111-4a4a-8a8a-000000000001'],
        );
    }

    public function test_a_refresh_token_rotates_only_for_its_own_client(): void
    {
        $pair = $this->tokens->issue($this->user, AuthClient::App);

        $this->assertRefused(fn() => $this->tokens->rotate($pair->refreshToken, AuthClient::Portal), 'INVALID_REFRESH');
    }

    public function test_an_unused_refresh_token_lapses_after_thirty_days(): void
    {
        $pair = $this->tokens->issue($this->user, AuthClient::App);

        $this->travel(31)->days();

        $this->assertRefused(fn() => $this->tokens->rotate($pair->refreshToken, AuthClient::App), 'AUTH_SESSION_EXPIRED');
    }

    public function test_revoke_all_keeps_only_the_named_family(): void
    {
        $kept = $this->tokens->issue($this->user, AuthClient::App);
        $ended = $this->tokens->issue($this->user, AuthClient::App);

        $this->tokens->revokeAll($this->user, $kept->family);

        $this->withToken($kept->accessToken)
            ->getJson('api/v1/_probe')
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withToken($ended->accessToken)
            ->getJson('api/v1/_probe')
            ->assertUnauthorized();

        $this->assertRefused(fn() => $this->tokens->rotate($ended->refreshToken, AuthClient::App), 'AUTH_SESSION_EXPIRED');
    }

    private function assertRefused(callable $call, string $code): void
    {
        try {
            $call();
            $this->fail("Expected {$code}");
        } catch (ApiException $e) {
            $this->assertSame($code, $e->errorCode);
            $this->assertSame(401, $e->status->value);
        }
    }
}
