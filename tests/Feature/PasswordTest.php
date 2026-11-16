<?php

namespace Tests\Feature;

use App\Helpers\Url;
use App\Models\PasswordCodeResets;
use App\Models\User;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    public function test_can_request_password_reset(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->post(
            'api/auth/forgot-password',
            ['email' => $user->email]
        );

        $response->assertCreated();
    }

    public function test_can_check_password_reset_code(): void
    {
        $user = User::factory()->unverified()->create();

        $this->post(
            'api/auth/forgot-password',
            ['email' => $user->email]
        );

        $code = PasswordCodeResets::latest()->firstWhere('email', $user->email);

        $response = $this->post(
            'api/auth/reset-password/check-code',
            ['code' => $code->code]
        );

        $response->assertAccepted();
    }

    public function test_can_reset_password(): void
    {
        $user = User::factory()->unverified()->create();

        $this->post(
            'api/auth/forgot-password',
            ['email' => $user->email]
        );

        $code = PasswordCodeResets::latest()->firstWhere('email', $user->email);

        $response = $this->post(
            'api/auth/reset-password/check-code',
            [
                'code' => $code->code,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]
        );

        $response->assertAccepted();
    }

    public function test_can_reset_password_with_token(): void
    {
        $user = User::factory()->unverified()->create();

        $this->post(
            'api/auth/forgot-password',
            ['email' => $user->email]
        );

        $code = PasswordCodeResets::latest()->firstWhere('email', $user->email);

        $response = $this->post(
            'api/auth/reset-password/check-code',
            [
                'code' => Url::base64urlEncode($code->code . '|' . md5(time())),
                'password' => 'password',
                'password_confirmation' => 'password',
            ]
        );

        $response->assertAccepted();
    }
}
