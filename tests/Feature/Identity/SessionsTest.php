<?php

namespace Tests\Feature\Identity;

use App\Enums\Role;
use App\Models\SignIn;
use App\Models\User;
use Tests\TestCase;

class SessionsTest extends TestCase
{
    private User $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->patient = User::factory()
            ->app()
            ->create(['phone' => '08031234567']);
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{access_token: string, refresh_token: string}
     */
    private function signIn(array $headers = [], string $password = 'Password1'): array
    {
        return $this->withHeaders($headers)
            ->postJson('api/v1/auth/login', ['phone_number' => '08031234567', 'password' => $password, 'device_id' => fake()->uuid()])
            ->assertOk()
            ->json('data');
    }

    public function test_the_list_names_each_signed_in_device_and_marks_the_current_one(): void
    {
        $this->signIn(['X-Device-Name' => 'Tecno Spark 10', 'X-Device-Platform' => 'android']);
        $phone = $this->signIn(['X-Device-Name' => 'iPhone, iOS 18.1', 'X-Device-Platform' => 'ios']);

        $this->travel(1)->minutes();
        $this->app['auth']->forgetGuards();

        $this->withToken($phone['access_token'])
            ->getJson('api/v1/auth/sessions')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.next_cursor', null)
            ->assertJsonPath('data.items.0.device_name', 'iPhone, iOS 18.1')
            ->assertJsonPath('data.items.0.platform', 'ios')
            ->assertJsonPath('data.items.0.current', true)
            ->assertJsonPath('data.items.1.device_name', 'Tecno Spark 10')
            ->assertJsonPath('data.items.1.current', false)
            ->assertJsonStructure(['data' => ['items' => [['id', 'device_name', 'platform', 'signed_in_at', 'last_used_at', 'current']]]]);
    }

    public function test_a_browser_is_named_from_its_user_agent(): void
    {
        $admin = User::factory()
            ->portal(Role::Pharmacy)
            ->create(['email' => 'desk@pharmacy.ng']);

        $session = $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36'])
            ->postJson('api/v1/portal/auth/sign-in', ['email' => $admin->email, 'password' => 'Password1'])
            ->assertCreated()
            ->json('data');

        $this->withToken($session['access_token'])
            ->getJson('api/v1/portal/account/sessions')
            ->assertOk()
            ->assertJsonPath('data.items.0.device_name', 'Chrome on Mac')
            ->assertJsonPath('data.items.0.platform', 'web');
    }

    public function test_ending_one_session_kills_its_tokens_and_leaves_the_others(): void
    {
        $other = $this->signIn();
        $mine = $this->signIn();
        $otherId = SignIn::query()->orderBy('id')->value('uuid');

        $this->withToken($mine['access_token'])
            ->deleteJson("api/v1/auth/sessions/{$otherId}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->postJson('api/v1/auth/refresh', ['refresh_token' => $other['refresh_token']])->assertUnauthorized();
        $this->postJson('api/v1/auth/refresh', ['refresh_token' => $mine['refresh_token']])->assertOk();
    }

    public function test_another_accounts_session_is_not_found(): void
    {
        $stranger = User::factory()
            ->app()
            ->create(['phone' => '08039999999']);

        $theirs = $this->postJson('api/v1/auth/login', ['phone_number' => '08039999999', 'password' => 'Password1', 'device_id' => 'x'])->json('data');
        $mine = $this->signIn();
        $theirId = SignIn::where('user_id', $stranger->id)->value('uuid');

        $this->withToken($mine['access_token'])
            ->deleteJson("api/v1/auth/sessions/{$theirId}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'SESSION_NOT_FOUND');

        $this->postJson('api/v1/auth/refresh', ['refresh_token' => $theirs['refresh_token']])->assertOk();
    }

    public function test_signing_out_everywhere_else_keeps_only_the_caller(): void
    {
        $first = $this->signIn();
        $second = $this->signIn();
        $mine = $this->signIn();

        $this->withToken($mine['access_token'])
            ->deleteJson('api/v1/auth/sessions')
            ->assertOk();

        $this->postJson('api/v1/auth/refresh', ['refresh_token' => $first['refresh_token']])->assertUnauthorized();
        $this->postJson('api/v1/auth/refresh', ['refresh_token' => $second['refresh_token']])->assertUnauthorized();

        $this->app['auth']->forgetGuards();

        $this->withToken($mine['access_token'])
            ->getJson('api/v1/auth/sessions')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.current', true);
    }

    public function test_a_password_change_ends_the_other_sessions_and_the_list_shows_it(): void
    {
        $this->signIn();
        $mine = $this->signIn();

        $this->withToken($mine['access_token'])
            ->postJson('api/v1/auth/change-password', ['current_password' => 'Password1', 'new_password' => 'Newpass99'])
            ->assertOk();

        $this->assertSame(1, SignIn::query()->live()->count());
    }

    public function test_a_refresh_keeps_the_session_and_moves_its_last_use(): void
    {
        $pair = $this->signIn();

        $this->travel(20)->minutes();

        $next = $this->postJson('api/v1/auth/refresh', ['refresh_token' => $pair['refresh_token']])
            ->assertOk()
            ->json('data');

        $this->assertSame(1, SignIn::count());
        $this->assertTrue(SignIn::first()->last_used_at->greaterThan(SignIn::first()->signed_in_at));

        $this->withToken($next['access_token'])
            ->getJson('api/v1/auth/sessions')
            ->assertJsonPath('data.items.0.current', true);
    }

    public function test_the_routes_need_the_right_kind_of_account(): void
    {
        $this->getJson('api/v1/auth/sessions')->assertUnauthorized();

        $desk = User::factory()
            ->portal(Role::Laboratory)
            ->create();

        $this->actingAs($desk, 'sanctum')
            ->getJson('api/v1/auth/sessions')
            ->assertForbidden();

        $this->actingAs($this->patient, 'sanctum')
            ->getJson('api/v1/portal/account/sessions')
            ->assertForbidden();
    }
}
