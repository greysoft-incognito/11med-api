<?php

namespace Tests\Feature\Identity;

use App\Enums\SocialProvider;
use App\Exceptions\ApiException;
use App\Models\ConsentRecord;
use App\Models\User;
use App\Services\Identity\Social\IdentityClaims;
use App\Services\Identity\Social\IdentityTokenVerifier;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config(['elevenmed.otp.fixed_code' => '123456']);

        // A token here is `subject|email|verified|name`; "bad" is refused.
        $this->app->instance(IdentityTokenVerifier::class, new class implements IdentityTokenVerifier
        {
            public function verify(SocialProvider $provider, string $idToken): IdentityClaims
            {
                if ($idToken === 'bad') {
                    throw ApiException::unauthorized('SOCIAL_TOKEN_INVALID');
                }

                [$subject, $email, $verified, $name] = explode('|', $idToken) + ['', '', '', ''];

                return new IdentityClaims($provider, $subject, $email ?: null, $verified === '1', $name ?: null);
            }
        });
    }

    private function social(string $token, array $extra = []): TestResponse
    {
        return $this->postJson('api/v1/auth/social', $extra + ['provider' => 'google', 'id_token' => $token, 'device_id' => 'x']);
    }

    private function link(string $linkToken, array $extra = []): TestResponse
    {
        return $this->postJson('api/v1/auth/social/phone', $extra + [
            'link_token' => $linkToken,
            'phone_number' => '08031234567',
            'full_name' => 'Amaka Obi',
            'role' => 'patient',
        ]);
    }

    public function test_a_new_identity_needs_a_phone_then_signs_in_directly(): void
    {
        $linkToken = $this->social('sub-1|amaka@gmail.com|1|Amaka O.')
            ->assertOk()
            ->assertJsonPath('data.status', 'phone_required')
            ->assertJsonPath('data.suggested_full_name', 'Amaka O.')
            ->json('data.link_token');

        $this->link($linkToken)
            ->assertCreated()
            ->assertJsonPath('data.phone_number', '08031234567')
            ->assertJsonPath('data.otp_expires_in', 600);

        $user = User::firstWhere('phone', '08031234567');

        $this->assertNull($user->password);
        $this->assertSame('amaka@gmail.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('social-link', ConsentRecord::firstWhere('user_id', $user->id)->context->value);

        $this->social('sub-1')->assertJsonPath('data.status', 'phone_required');

        $this->postJson('api/v1/auth/verify-otp', ['phone_number' => '08031234567', 'code' => '123456'])
            ->assertOk()
            ->assertJsonPath('data.user.email_verified', true);

        $this->social('sub-1')
            ->assertOk()
            ->assertJsonPath('data.status', 'authenticated')
            ->assertJsonPath('data.session.user.full_name', 'Amaka Obi')
            ->assertJsonStructure(['data' => ['session' => ['access_token', 'refresh_token']]]);
    }

    public function test_an_unverified_claim_lands_as_an_unverified_address(): void
    {
        $linkToken = $this->social('sub-1|amaka@gmail.com|0|')->json('data.link_token');

        $this->link($linkToken)->assertCreated();

        $this->assertNull(User::firstWhere('phone', '08031234567')->email_verified_at);
    }

    public function test_an_address_already_on_an_account_is_not_copied(): void
    {
        User::factory()
            ->app()
            ->create(['email' => 'amaka@gmail.com']);

        $linkToken = $this->social('sub-1|amaka@gmail.com|1|')->json('data.link_token');

        $this->link($linkToken)->assertCreated();

        $this->assertNull(User::firstWhere('phone', '08031234567')->email);
    }

    public function test_a_number_with_an_account_is_a_conflict_not_a_merge(): void
    {
        User::factory()
            ->app()
            ->create(['phone' => '08031234567']);

        $linkToken = $this->social('sub-1')->json('data.link_token');

        $this->link($linkToken)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PHONE_ALREADY_REGISTERED')
            ->assertJsonPath('error.field', 'phone_number');
    }

    public function test_a_link_token_is_single_use_and_lapses(): void
    {
        $linkToken = $this->social('sub-1')->json('data.link_token');

        $this->link($linkToken)->assertCreated();

        $this->link($linkToken, ['phone_number' => '08031234568'])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_SESSION_EXPIRED');

        $late = $this->social('sub-2')->json('data.link_token');

        $this->travel(16)->minutes();

        $this->link($late, ['phone_number' => '08031234569'])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_SESSION_EXPIRED');
    }

    public function test_a_bad_token_or_provider_is_refused(): void
    {
        $this->social('bad')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'SOCIAL_TOKEN_INVALID');

        $this->social('sub-1', ['provider' => 'facebook'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'provider');
    }
}
