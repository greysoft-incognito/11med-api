<?php

namespace Tests\Unit;

use App\Enums\SocialProvider;
use App\Exceptions\ApiException;
use App\Services\Identity\Social\JwksIdentityTokenVerifier;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JwksIdentityTokenVerifierTest extends TestCase
{
    private string $privateKey;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => 'web-client.apps.googleusercontent.com']);

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $this->privateKey = $pem;
        $details = openssl_pkey_get_details($key);

        Http::fake([
            'www.googleapis.com/*' => Http::response(['keys' => [[
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'kid' => 'k1',
                'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
                'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
            ]]]),
        ]);
    }

    private function token(array $claims): string
    {
        return JWT::encode($claims + [
            'iss' => 'https://accounts.google.com',
            'aud' => 'web-client.apps.googleusercontent.com',
            'sub' => '1234',
            'exp' => time() + 600,
            'email' => 'amaka@gmail.com',
            'email_verified' => true,
            'name' => 'Amaka Obi',
        ], $this->privateKey, 'RS256', 'k1');
    }

    public function test_a_signed_token_for_our_audience_is_accepted(): void
    {
        $claims = (new JwksIdentityTokenVerifier)->verify(SocialProvider::Google, $this->token([]));

        $this->assertSame('1234', $claims->subject);
        $this->assertSame('amaka@gmail.com', $claims->email);
        $this->assertTrue($claims->emailVerified);
        $this->assertSame('Amaka Obi', $claims->name);
    }

    public function test_another_audience_issuer_or_an_expired_token_is_refused(): void
    {
        foreach ([['aud' => 'ios-client'], ['iss' => 'https://evil.example'], ['exp' => time() - 10]] as $claims) {
            try {
                (new JwksIdentityTokenVerifier)->verify(SocialProvider::Google, $this->token($claims));
                $this->fail('Expected a refusal');
            } catch (ApiException $e) {
                $this->assertSame('SOCIAL_TOKEN_INVALID', $e->errorCode);
            }
        }
    }

    public function test_without_a_configured_audience_the_provider_is_unavailable(): void
    {
        config(['services.google.client_id' => null]);

        $this->expectExceptionObject(ApiException::unprocessable('SOCIAL_UNAVAILABLE', 'provider'));

        (new JwksIdentityTokenVerifier)->verify(SocialProvider::Google, $this->token([]));
    }
}
